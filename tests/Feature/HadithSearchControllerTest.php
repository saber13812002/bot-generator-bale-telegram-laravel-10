<?php

namespace Tests\Feature;

use App\Helpers\WebhookMockHelper;
use App\Models\BotHadithItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hadith webhook — mirrors the WebhookTest pattern:
 * POST /api/webhook-hadith with query params (origin, token, ...) and a
 * JSON body carrying the platform update.
 *
 * The controller always returns HTTP 200 (it catches every exception and
 * replies with "0" for a handled error / "1" when it sent a long message
 * itself), so these tests verify status + graceful degradation. The
 * "test_token" is not a real bot token, so all bot API calls fail softly
 * and no network I/O is required.
 */
class HadithSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/webhook-hadith';

    protected function setUp(): void
    {
        parent::setUp();
        BotHadithItem::factory()->count(10)->create();
    }

    public function testIndexWithBaleOrigin()
    {
        $update = WebhookMockHelper::mockBaleUpdate('/start');

        $response = $this->postJson(
            self::URL . '?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    public function testIndexWithTelegramOrigin()
    {
        $update = WebhookMockHelper::mockTelegramUpdate('/start');

        $response = $this->postJson(
            self::URL . '?origin=telegram&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    public function testRandomCommand()
    {
        $update = WebhookMockHelper::mockTelegramUpdate('/random');

        $response = $this->postJson(
            self::URL . '?origin=telegram&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    public function testSearchCommand()
    {
        $update = WebhookMockHelper::mockTelegramUpdate('/search');

        $response = $this->postJson(
            self::URL . '?origin=telegram&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    public function testHadithIdRequest()
    {
        $update = WebhookMockHelper::mockTelegramUpdate('/_id:some_id');

        $response = $this->postJson(
            self::URL . '?origin=telegram&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    public function testPhraseSearchIsHandledGracefully()
    {
        // Plain (non-command) text enters the external search path; with a
        // fake token everything fails softly and the controller must still
        // answer 200 (never 500).
        $update = WebhookMockHelper::mockBaleUpdate('صبر');

        $response = $this->postJson(
            self::URL . '?origin=bale&token=test_token&bot_mother_id=1&language=fa',
            $update
        );

        $response->assertStatus(200);
    }

    public function testIndexHandlesExceptionGracefully()
    {
        // Even when the bot data cannot be parsed (invalid token, malformed
        // flow) the webhook must degrade gracefully with HTTP 200.
        $response = $this->postJson(
            self::URL . '?origin=telegram&token=test_token&bot_mother_id=1&language=fa',
            []
        );

        $response->assertStatus(200);
    }
}
