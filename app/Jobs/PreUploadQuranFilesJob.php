<?php

namespace App\Jobs;

use App\Helpers\FileUploadHelper;
use App\Helpers\QuranHelper;
use App\Helpers\StringHelper;
use App\Models\Bot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram;

class PreUploadQuranFilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $botId;
    public string $botType;
    public ?int $limit;

    /**
     * Create a new job instance.
     */
    public function __construct(int $botId, string $botType, ?int $limit = null)
    {
        $this->botId = $botId;
        $this->botType = $botType;
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $bot = Bot::find($this->botId);
            if (!$bot) {
                Log::error('❌ [PreUploadQuranFilesJob] Bot not found', [
                    'bot_id' => $this->botId
                ]);
                return;
            }

            // دریافت token بر اساس bot_type
            $token = null;
            if ($this->botType == 'telegram') {
                $token = $bot->telegram_bot_token;
            } elseif ($this->botType == 'bale') {
                $token = $bot->bale_bot_token;
            }

            if (!$token) {
                Log::error('❌ [PreUploadQuranFilesJob] Token not found', [
                    'bot_id' => $this->botId,
                    'bot_type' => $this->botType
                ]);
                return;
            }

            // ایجاد messenger instance
            $messenger = new Telegram($token, $this->botType == 'bale' ? 'bale' : null);

            // آپلود اسکن صفحات
            $this->uploadScanPages($messenger);

            // آپلود فایل‌های صوتی قرائت
            $this->uploadAudioRecitations($messenger);

            // آپلود فایل‌های صوتی صفحه
            $this->uploadAudioPages($messenger);

            Log::info('✅ [PreUploadQuranFilesJob] Completed', [
                'bot_id' => $this->botId,
                'bot_type' => $this->botType
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [PreUploadQuranFilesJob] Exception', [
                'bot_id' => $this->botId,
                'bot_type' => $this->botType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * آپلود اسکن صفحات قرآن
     */
    private function uploadScanPages(Telegram $messenger): void
    {
        Log::info('📄 [PreUploadQuranFilesJob] Starting scan pages upload', [
            'bot_id' => $this->botId,
            'bot_type' => $this->botType
        ]);

        $hrs = [1, 2, 3, 4]; // 4 CDN مختلف
        $totalPages = 604;
        $uploaded = 0;
        $skipped = 0;

        foreach ($hrs as $hr) {
            $maxPage = $this->limit ? min($this->limit, $totalPages) : $totalPages;
            
            for ($page = 1; $page <= $maxPage; $page++) {
                try {
                    $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('scan_page', [
                        'hr' => $hr,
                        'page' => $page
                    ], $this->botType);

                    // چک کردن آیا فایل قبلاً آپلود شده است
                    $existingFileId = FileUploadHelper::getFileId($this->botId, $this->botType, $fileUniqueKey);
                    
                    if ($existingFileId) {
                        $skipped++;
                        continue;
                    }

                    // دریافت URL فایل
                    $photoUrl = QuranHelper::getScanFullUrl($page, $hr, $this->botType);

                    // آپلود فایل
                    $fileInfo = FileUploadHelper::getOrUploadFile(
                        $messenger,
                        $fileUniqueKey,
                        $photoUrl,
                        'scan_page',
                        [
                            'hr' => $hr,
                            'page' => $page,
                            'bot_type' => $this->botType
                        ],
                        $this->botId,
                        $this->botType
                    );

                    if ($fileInfo) {
                        $uploaded++;
                        Log::info('✅ [PreUploadQuranFilesJob] Scan page uploaded', [
                            'bot_id' => $this->botId,
                            'hr' => $hr,
                            'page' => $page,
                            'file_id' => $fileInfo['file_id']
                        ]);
                    }

                    // Delay برای جلوگیری از rate limiting
                    usleep(500000); // 0.5 second
                } catch (\Exception $e) {
                    Log::error('❌ [PreUploadQuranFilesJob] Error uploading scan page', [
                        'bot_id' => $this->botId,
                        'hr' => $hr,
                        'page' => $page,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Log::info('✅ [PreUploadQuranFilesJob] Scan pages upload completed', [
            'bot_id' => $this->botId,
            'uploaded' => $uploaded,
            'skipped' => $skipped
        ]);
    }

    /**
     * آپلود فایل‌های صوتی قرائت
     */
    private function uploadAudioRecitations(Telegram $messenger): void
    {
        Log::info('🎵 [PreUploadQuranFilesJob] Starting audio recitations upload', [
            'bot_id' => $this->botId,
            'bot_type' => $this->botType
        ]);

        $reciters = ['parhizgar', 'alafasy'];
        $totalSuras = 114;
        $uploaded = 0;
        $skipped = 0;

        foreach ($reciters as $reciter) {
            // برای هر reciter، باید تمام آیات را آپلود کنیم
            // این کار زمان‌بر است، پس فقط چند سوره اول را آپلود می‌کنیم (مگر limit مشخص شده باشد)
            $maxSura = $this->limit ? min($this->limit, $totalSuras) : min(10, $totalSuras); // پیش‌فرض 10 سوره

            for ($sura = 1; $sura <= $maxSura; $sura++) {
                // تعداد آیات هر سوره متفاوت است، برای سادگی فقط 10 آیه اول را آپلود می‌کنیم
                $maxAya = $this->limit ? min($this->limit, 286) : 10; // پیش‌فرض 10 آیه

                for ($aya = 1; $aya <= $maxAya; $aya++) {
                    try {
                        $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('audio_recitation', [
                            'reciter' => $reciter,
                            'sura' => $sura,
                            'aya' => $aya
                        ], $this->botType);

                        // چک کردن آیا فایل قبلاً آپلود شده است
                        $existingFileId = FileUploadHelper::getFileId($this->botId, $this->botType, $fileUniqueKey);
                        
                        if ($existingFileId) {
                            $skipped++;
                            continue;
                        }

                        // دریافت URL فایل
                        $aye = \App\Models\QuranAyat::query()
                            ->whereSura($sura)
                            ->whereAya($aya)
                            ->first();

                        if (!$aye) {
                            continue;
                        }

                        $audioUrl = QuranHelper::getAudioUrl($reciter, $aye);

                        // آپلود فایل
                        $fileInfo = FileUploadHelper::getOrUploadFile(
                            $messenger,
                            $fileUniqueKey,
                            $audioUrl,
                            'audio_recitation',
                            [
                                'reciter' => $reciter,
                                'sura' => $sura,
                                'aya' => $aya
                            ],
                            $this->botId,
                            $this->botType
                        );

                        if ($fileInfo) {
                            $uploaded++;
                        }

                        // Delay برای جلوگیری از rate limiting
                        usleep(500000); // 0.5 second
                    } catch (\Exception $e) {
                        Log::error('❌ [PreUploadQuranFilesJob] Error uploading audio recitation', [
                            'bot_id' => $this->botId,
                            'reciter' => $reciter,
                            'sura' => $sura,
                            'aya' => $aya,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        Log::info('✅ [PreUploadQuranFilesJob] Audio recitations upload completed', [
            'bot_id' => $this->botId,
            'uploaded' => $uploaded,
            'skipped' => $skipped
        ]);
    }

    /**
     * آپلود فایل‌های صوتی صفحه
     */
    private function uploadAudioPages(Telegram $messenger): void
    {
        Log::info('🎵 [PreUploadQuranFilesJob] Starting audio pages upload', [
            'bot_id' => $this->botId,
            'bot_type' => $this->botType
        ]);

        $totalPages = 604;
        $maxPage = $this->limit ? min($this->limit, $totalPages) : $totalPages;
        $uploaded = 0;
        $skipped = 0;

        for ($page = 1; $page <= $maxPage; $page++) {
            try {
                $fileUniqueKey = FileUploadHelper::generateFileUniqueKey('audio_page', [
                    'page' => $page
                ], $this->botType);

                // چک کردن آیا فایل قبلاً آپلود شده است
                $existingFileId = FileUploadHelper::getFileId($this->botId, $this->botType, $fileUniqueKey);
                
                if ($existingFileId) {
                    $skipped++;
                    continue;
                }

                // دریافت URL فایل
                $base_url = "https://ia800304.us.archive.org/32/items/quran-by--maher-alm3eaqli---128-kb----604-part-full-quran-604-page--safahat-mp3/Page";
                $audioUrl = $base_url . $page . ".mp3";

                // آپلود فایل
                $fileInfo = FileUploadHelper::getOrUploadFile(
                    $messenger,
                    $fileUniqueKey,
                    $audioUrl,
                    'audio_page',
                    [
                        'page' => $page
                    ],
                    $this->botId,
                    $this->botType
                );

                if ($fileInfo) {
                    $uploaded++;
                }

                // Delay برای جلوگیری از rate limiting
                usleep(500000); // 0.5 second
            } catch (\Exception $e) {
                Log::error('❌ [PreUploadQuranFilesJob] Error uploading audio page', [
                    'bot_id' => $this->botId,
                    'page' => $page,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('✅ [PreUploadQuranFilesJob] Audio pages upload completed', [
            'bot_id' => $this->botId,
            'uploaded' => $uploaded,
            'skipped' => $skipped
        ]);
    }
}
