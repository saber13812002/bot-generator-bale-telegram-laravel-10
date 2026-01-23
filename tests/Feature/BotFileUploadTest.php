<?php

namespace Tests\Feature;

use App\Helpers\FileUploadHelper;
use App\Models\Bot;
use App\Models\BotUploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotFileUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * تست ارسال اسکن صفحه با file_id موجود
     */
    public function test_send_scan_page_with_existing_file_id(): void
    {
        // ایجاد bot
        $bot = Bot::factory()->create([
            'telegram_bot_token' => 'test_token',
            'telegram_bot_status' => 'Active'
        ]);

        // ایجاد فایل آپلود شده
        BotUploadedFile::create([
            'bot_id' => $bot->id,
            'bot_type' => 'telegram',
            'file_unique_key' => 'scan_page_1_1_telegram',
            'file_type' => 'scan_page',
            'file_id' => 'test_file_id_123',
            'file_unique_id' => 'test_unique_id_123',
            'width' => 200,
            'height' => 200,
            'file_size' => 2000
        ]);

        // چک کردن که file_id موجود است
        $fileId = FileUploadHelper::getFileId($bot->id, 'telegram', 'scan_page_1_1_telegram');

        $this->assertEquals('test_file_id_123', $fileId);
    }

    /**
     * تست ارسال فایل صوتی با file_id موجود
     */
    public function test_send_audio_with_existing_file_id(): void
    {
        $bot = Bot::factory()->create([
            'telegram_bot_token' => 'test_token',
            'telegram_bot_status' => 'Active'
        ]);

        BotUploadedFile::create([
            'bot_id' => $bot->id,
            'bot_type' => 'telegram',
            'file_unique_key' => 'audio_recitation_parhizgar_1_1_telegram',
            'file_type' => 'audio_recitation',
            'file_id' => 'test_audio_file_id_123',
            'file_unique_id' => 'test_audio_unique_id_123'
        ]);

        $fileId = FileUploadHelper::getFileId($bot->id, 'telegram', 'audio_recitation_parhizgar_1_1_telegram');

        $this->assertEquals('test_audio_file_id_123', $fileId);
    }

    /**
     * تست آپلود فایل جدید
     */
    public function test_upload_new_file(): void
    {
        $bot = Bot::factory()->create([
            'telegram_bot_token' => 'test_token',
            'telegram_bot_status' => 'Active'
        ]);

        // چک کردن که فایل وجود ندارد
        $fileId = FileUploadHelper::getFileId($bot->id, 'telegram', 'scan_page_1_1_telegram');
        $this->assertNull($fileId);

        // ذخیره فایل جدید
        $response = [
            'ok' => true,
            'result' => [
                'photo' => [
                    [
                        'file_id' => 'new_file_id_1',
                        'file_unique_id' => 'new_unique_id_1',
                        'width' => 100,
                        'height' => 100,
                        'file_size' => 1000
                    ],
                    [
                        'file_id' => 'new_file_id_2',
                        'file_unique_id' => 'new_unique_id_2',
                        'width' => 200,
                        'height' => 200,
                        'file_size' => 2000
                    ]
                ]
            ]
        ];

        $uploadedFile = FileUploadHelper::saveUploadedFile(
            $bot->id,
            'telegram',
            'scan_page_1_1_telegram',
            'scan_page',
            $response,
            [
                'hr' => 1,
                'page' => 1
            ]
        );

        $this->assertNotNull($uploadedFile);
        $this->assertEquals('new_file_id_2', $uploadedFile->file_id);

        // چک کردن که فایل ذخیره شده است
        $savedFileId = FileUploadHelper::getFileId($bot->id, 'telegram', 'scan_page_1_1_telegram');
        $this->assertEquals('new_file_id_2', $savedFileId);
    }

    /**
     * تست یونیک بودن file_unique_key برای هر ربات
     */
    public function test_unique_file_key_per_bot(): void
    {
        $bot1 = Bot::factory()->create([
            'telegram_bot_token' => 'test_token_1',
            'telegram_bot_status' => 'Active'
        ]);

        $bot2 = Bot::factory()->create([
            'telegram_bot_token' => 'test_token_2',
            'telegram_bot_status' => 'Active'
        ]);

        // ذخیره فایل برای bot1
        BotUploadedFile::create([
            'bot_id' => $bot1->id,
            'bot_type' => 'telegram',
            'file_unique_key' => 'scan_page_1_1_telegram',
            'file_type' => 'scan_page',
            'file_id' => 'bot1_file_id',
            'file_unique_id' => 'bot1_unique_id'
        ]);

        // ذخیره فایل برای bot2 با همان file_unique_key
        BotUploadedFile::create([
            'bot_id' => $bot2->id,
            'bot_type' => 'telegram',
            'file_unique_key' => 'scan_page_1_1_telegram',
            'file_type' => 'scan_page',
            'file_id' => 'bot2_file_id',
            'file_unique_id' => 'bot2_unique_id'
        ]);

        // چک کردن که هر ربات file_id خودش را دارد
        $fileId1 = FileUploadHelper::getFileId($bot1->id, 'telegram', 'scan_page_1_1_telegram');
        $fileId2 = FileUploadHelper::getFileId($bot2->id, 'telegram', 'scan_page_1_1_telegram');

        $this->assertEquals('bot1_file_id', $fileId1);
        $this->assertEquals('bot2_file_id', $fileId2);
        $this->assertNotEquals($fileId1, $fileId2);
    }
}
