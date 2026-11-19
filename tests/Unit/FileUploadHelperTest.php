<?php

namespace Tests\Unit;

use App\Helpers\FileUploadHelper;
use App\Models\Bot;
use App\Models\BotUploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Telegram;

class FileUploadHelperTest extends TestCase
{
    use RefreshDatabase;

    /**
     * تست ساخت file_unique_key برای اسکن صفحه
     */
    public function test_generate_file_unique_key_for_scan_page(): void
    {
        $key = FileUploadHelper::generateFileUniqueKey('scan_page', [
            'hr' => 1,
            'page' => 1
        ], 'telegram');

        $this->assertEquals('scan_page_1_1_telegram', $key);
    }

    /**
     * تست ساخت file_unique_key برای فایل صوتی قرائت
     */
    public function test_generate_file_unique_key_for_audio_recitation(): void
    {
        $key = FileUploadHelper::generateFileUniqueKey('audio_recitation', [
            'reciter' => 'parhizgar',
            'sura' => 1,
            'aya' => 1
        ], 'bale');

        $this->assertEquals('audio_recitation_parhizgar_1_1_bale', $key);
    }

    /**
     * تست ساخت file_unique_key برای فایل صوتی ترجمه
     */
    public function test_generate_file_unique_key_for_audio_translation(): void
    {
        $key = FileUploadHelper::generateFileUniqueKey('audio_translation', [
            'locale' => 'fa.makarem',
            'sura' => 1,
            'aya' => 1
        ], 'telegram');

        $this->assertEquals('audio_translation_fa.makarem_1_1_telegram', $key);
    }

    /**
     * تست ساخت file_unique_key برای فایل صوتی صفحه
     */
    public function test_generate_file_unique_key_for_audio_page(): void
    {
        $key = FileUploadHelper::generateFileUniqueKey('audio_page', [
            'page' => 1
        ], 'bale');

        $this->assertEquals('audio_page_1_bale', $key);
    }

    /**
     * تست دریافت file_id از دیتابیس
     */
    public function test_get_file_id_from_database(): void
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
            'file_unique_id' => 'test_unique_id_123'
        ]);

        // دریافت file_id
        $fileId = FileUploadHelper::getFileId($bot->id, 'telegram', 'scan_page_1_1_telegram');

        $this->assertEquals('test_file_id_123', $fileId);
    }

    /**
     * تست دریافت file_id که وجود ندارد
     */
    public function test_get_file_id_not_found(): void
    {
        $bot = Bot::factory()->create([
            'telegram_bot_token' => 'test_token',
            'telegram_bot_status' => 'Active'
        ]);

        $fileId = FileUploadHelper::getFileId($bot->id, 'telegram', 'non_existent_key');

        $this->assertNull($fileId);
    }

    /**
     * تست ذخیره فایل آپلود شده
     */
    public function test_save_uploaded_file(): void
    {
        $bot = Bot::factory()->create([
            'telegram_bot_token' => 'test_token',
            'telegram_bot_status' => 'Active'
        ]);

        $response = [
            'ok' => true,
            'result' => [
                'photo' => [
                    [
                        'file_id' => 'test_file_id_1',
                        'file_unique_id' => 'test_unique_id_1',
                        'width' => 100,
                        'height' => 100,
                        'file_size' => 1000
                    ],
                    [
                        'file_id' => 'test_file_id_2',
                        'file_unique_id' => 'test_unique_id_2',
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
        $this->assertEquals('test_file_id_2', $uploadedFile->file_id);
        $this->assertEquals('test_unique_id_2', $uploadedFile->file_unique_id);
        $this->assertEquals(200, $uploadedFile->width);
        $this->assertEquals(200, $uploadedFile->height);
        $this->assertEquals(2000, $uploadedFile->file_size);
    }
}
