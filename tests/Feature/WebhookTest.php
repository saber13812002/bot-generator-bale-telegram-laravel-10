<?php

namespace Tests\Feature;

use App\Helpers\WebhookDevHelper;
use App\Helpers\WebhookMockHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * تست webhook با پیام "salam"
     */
    public function test_webhook_salam_message(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('salam');

        $response = $this->postJson(
            '/api/webhook-bot-mother?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook با دستور /start
     */
    public function test_webhook_start_command(): void
    {
        $update = WebhookMockHelper::mockStartCommand();

        $response = $this->postJson(
            '/api/webhook-bot-mother?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook با دستور /new_bot
     */
    public function test_webhook_new_bot_command(): void
    {
        $update = WebhookMockHelper::mockNewBotCommand();

        $response = $this->postJson(
            '/api/webhook-bot-mother?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook با ارسال token
     */
    public function test_webhook_send_token(): void
    {
        $token = '737102910:Kj1bsD3XeCEjnOPcVBOmrwlGNON7BYPTd171L8Qj';
        $update = WebhookMockHelper::mockTokenUpdate($token);

        $response = $this->postJson(
            '/api/webhook-bot-mother?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook users با query params
     */
    public function test_webhook_users_with_query_params(): void
    {
        $update = WebhookMockHelper::mockStartCommand();

        $response = $this->postJson(
            '/api/webhook-bot-children?bot_user_name=Testchannelbot&bot_token=test_token&origin=bale',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook callback query (کلیک روی دکمه)
     */
    public function test_webhook_callback_query(): void
    {
        $update = WebhookMockHelper::mockCallbackQuery('/1');

        $response = $this->postJson(
            '/api/webhook-quran-word?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook قرآن با دستور /1
     */
    public function test_webhook_quran_word(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('/1');

        $response = $this->postJson(
            '/api/webhook-quran-word?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook قرآن با جستجو
     */
    public function test_webhook_quran_ayat(): void
    {
        $update = WebhookMockHelper::mockQuranSearch('/sure2ayah2');

        $response = $this->postJson(
            '/api/webhook-quran-ayat?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook هواشناسی
     */
    public function test_webhook_weather(): void
    {
        $update = WebhookMockHelper::mockWeatherCommand('/current');

        $response = $this->postJson(
            '/api/webhook-weather?origin=bale&token=test_token',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook حدیث با جستجو
     */
    public function test_webhook_hadith_search(): void
    {
        $update = WebhookMockHelper::mockHadithSearch('صبر');

        $response = $this->postJson(
            '/api/webhook-hadith?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست webhook نهج البلاغه
     */
    public function test_webhook_nahj(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('/search صبر');

        $response = $this->postJson(
            '/api/webhook-nahj?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    /**
     * تست validation برای origin
     */
    public function test_webhook_validation_origin_required(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('test');

        $response = $this->postJson(
            '/api/webhook-bot-mother?token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(422);
    }

    /**
     * تست validation برای token
     */
    public function test_webhook_validation_token_required(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('test');

        $response = $this->postJson(
            '/api/webhook-bot-mother?origin=bale&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(422);
    }

    /**
     * تست با WebhookDevHelper برای شبیه‌سازی
     */
    public function test_webhook_with_dev_helper(): void
    {
        $response = WebhookDevHelper::quickTest(
            '/api/webhook-bot-mother',
            'salam',
            [
                'origin' => 'bale',
                'token' => 'test_token',
                'bot_mother_id' => 1,
                'language' => 'fa'
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * تست callback query با WebhookDevHelper
     */
    public function test_callback_query_with_dev_helper(): void
    {
        $response = WebhookDevHelper::testCallbackQuery(
            '/api/webhook-quran-word',
            '/1',
            [
                'origin' => 'bale',
                'token' => 'test_token',
                'bot_mother_id' => 1,
                'language' => 'fa'
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * تست ساختار update
     */
    public function test_validate_update_structure(): void
    {
        $update = WebhookMockHelper::mockBaleUpdate('test');
        $this->assertTrue(WebhookDevHelper::validateUpdateStructure($update));

        $callbackUpdate = WebhookMockHelper::mockCallbackQuery('/1');
        $this->assertTrue(WebhookDevHelper::validateUpdateStructure($callbackUpdate));

        $invalidUpdate = ['invalid' => 'data'];
        $this->assertFalse(WebhookDevHelper::validateUpdateStructure($invalidUpdate));
    }

    /**
     * تست با Telegram به جای Bale
     */
    public function test_webhook_telegram(): void
    {
        $update = WebhookMockHelper::mockTelegramUpdate('/start');

        $response = $this->postJson(
            '/api/webhook-bot-mother?origin=telegram&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }
}

