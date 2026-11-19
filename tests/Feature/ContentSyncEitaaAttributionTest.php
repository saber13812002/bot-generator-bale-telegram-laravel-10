<?php

namespace Tests\Feature;

use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use App\Models\ContentSyncContent;
use App\Services\ChannelPosterQueuePublishService;
use App\Services\ContentSync\ContentSyncService;
use App\Http\Controllers\ChannelPosterBotController;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-CHANNEL-HANDLE-SIGNATURE §3: لینک پست اصلی برای مقاصد ثانویِ ایتا.
 *
 * Mandatory coverage:
 *   1. secondary eitaa destination gets exactly:
 *        <main text>
 *
 *        لینک این پست در کانال اصلی:
 *        <source url>
 *      (title once, url once, main text unchanged)
 *   2. regression: bale/telegram destinations keep the old «منبع» label
 *   3. dedup: source URL already in the text → nothing appended (no orphan title)
 *   4. link_only stays URL-only for eitaa (no special title)
 *   5. queue mode: frozen attribution renders the same eitaa title
 */
class ContentSyncEitaaAttributionTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    private string $token = '123:channel-poster-token';

    private string $ownerChatId = '111';

    private const SOURCE_URL = 'https://eitaa.com/alirezamehran_3/2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();

        $this->publisher = new FakeChannelPosterPublisher();
        $this->app->instance(
            ChannelPosterPublisherFactory::class,
            new FakeChannelPosterPublisherFactory($this->publisher)
        );
    }

    // ------------------------------------------------------------------
    // 1+2. Full webhook flow: secondary eitaa gets the exact titled link,
    //      other platforms keep the old attribution label.
    // ------------------------------------------------------------------
    public function test_secondary_eitaa_destination_gets_exact_titled_original_link(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postText($this->ownerChatId, self::SOURCE_URL);
        $this->postText($this->ownerChatId, 'مطلب مطلب مطلب مطلب مطلب');

        $this->assertCount(3, $this->publisher->publishes);
        $byChannel = [];
        foreach ($this->publisher->publishes as $pub) {
            $byChannel[$pub['channel_chat_id']] = $pub;
        }

        $title = trans('bot.channel_sync_eitaa_original_post_label');

        // secondary eitaa — the exact mission output
        $this->assertArrayHasKey('alirezamehran_4', $byChannel);
        $eitaaText = $byChannel['alirezamehran_4']['text'];
        $this->assertSame(
            'مطلب مطلب مطلب مطلب مطلب' . "\n\n" . $title . "\n" . self::SOURCE_URL,
            $eitaaText,
            'secondary eitaa must get: main text, blank line, exact title, source URL'
        );
        $this->assertSame(1, substr_count($eitaaText, $title), 'title must appear exactly once');
        $this->assertSame(1, substr_count($eitaaText, self::SOURCE_URL), 'source URL must appear exactly once');
        $this->assertStringStartsWith('مطلب مطلب مطلب مطلب مطلب', $eitaaText, 'main text unchanged');
        $this->assertStringNotContainsString('<a', $eitaaText);

        // regression: bale + telegram keep the old «منبع» label (no special title)
        foreach (['8995754940207167599', '@saberblog'] as $chatId) {
            $this->assertArrayHasKey($chatId, $byChannel);
            $label = trans('bot.channel_sync_source_label', [
                'channel' => 'Alireza',
                'platform' => trans('bot.channel_poster_sig_eitaa'),
            ]);
            $this->assertStringEndsWith($label . "\n" . self::SOURCE_URL, $byChannel[$chatId]['text']);
            $this->assertStringNotContainsString($title, $byChannel[$chatId]['text']);
        }
    }

    // ------------------------------------------------------------------
    // 3+4. Direct suffix calls: dedup (no orphan title), link_only, none
    // ------------------------------------------------------------------
    public function test_dedup_link_only_and_none_behaviors_via_direct_suffix(): void
    {
        $bot = $this->createBot();
        $service = $this->app->make(ContentSyncService::class);

        // URL not in the text
        $plain = ContentSyncContent::create([
            'bot_id' => $bot->id,
            'text' => 'متن بدون لینک',
            'source_platform' => 'eitaa',
            'source_channel_id' => 'alirezamehran_3',
            'source_message_id' => '10',
            'source_url' => self::SOURCE_URL,
            'tag' => 'tag1',
        ]);

        $title = trans('bot.channel_sync_eitaa_original_post_label');

        // signed + secondary eitaa → exact titled link
        $this->assertSame(
            $title . "\n" . self::SOURCE_URL,
            $service->buildAttributionSuffix($plain, 'signed', 'eitaa')
        );

        // signed + non-eitaa destination → old label + url
        $label = trans('bot.channel_sync_source_label', [
            'channel' => 'alirezamehran_3',
            'platform' => trans('bot.channel_poster_sig_eitaa'),
        ]);
        $this->assertSame($label . "\n" . self::SOURCE_URL, $service->buildAttributionSuffix($plain, 'signed', 'bale'));

        // link_only stays URL-only — the special title must NOT be used
        $this->assertSame(self::SOURCE_URL, $service->buildAttributionSuffix($plain, 'link_only', 'eitaa'));

        // none → empty everywhere
        $this->assertSame('', $service->buildAttributionSuffix($plain, 'none', 'eitaa'));

        // URL already in the text (dedup) → NOTHING for eitaa (no orphan title)
        $withUrl = ContentSyncContent::create([
            'bot_id' => $bot->id,
            'text' => 'ببین این پست: ' . self::SOURCE_URL . ' و همین',
            'source_platform' => 'eitaa',
            'source_channel_id' => 'alirezamehran_3',
            'source_message_id' => '11',
            'source_url' => self::SOURCE_URL,
            'tag' => 'tag1',
        ]);

        $this->assertSame('', $service->buildAttributionSuffix($withUrl, 'signed', 'eitaa'), 'dedup must suppress the title too');
        // non-eitaa keeps the label (url itself is deduped by the existing rule)
        $this->assertSame($label, $service->buildAttributionSuffix($withUrl, 'signed', 'bale'));
    }

    // ------------------------------------------------------------------
    // 5. Queue mode: frozen attribution renders the eitaa title at publish time
    // ------------------------------------------------------------------
    public function test_queue_mode_secondary_eitaa_keeps_titled_attribution(): void
    {
        $bot = $this->createBot();
        $this->createDestinations($bot->id);

        $this->postCallback('cps:mode:queue');
        $this->postText($this->ownerChatId, self::SOURCE_URL);
        $this->postText($this->ownerChatId, 'متن صف ایتا ثانوی');

        $queue = ChannelPosterQueue::first();
        $this->assertNotNull($queue);
        $this->assertSame('signed', $queue->source_attribution);

        $service = $this->app->make(ChannelPosterQueuePublishService::class);
        $result = $service->publishItem($queue->fresh(), false);
        $this->assertSame('published', $result['status']);

        $this->assertCount(3, $this->publisher->publishes);
        $byChannel = [];
        foreach ($this->publisher->publishes as $pub) {
            $byChannel[$pub['channel_chat_id']] = $pub;
        }

        $title = trans('bot.channel_sync_eitaa_original_post_label');
        $this->assertSame(
            'متن صف ایتا ثانوی' . "\n\n" . $title . "\n" . self::SOURCE_URL,
            $byChannel['alirezamehran_4']['text']
        );

        $label = trans('bot.channel_sync_source_label', [
            'channel' => 'Alireza',
            'platform' => trans('bot.channel_poster_sig_eitaa'),
        ]);
        $this->assertStringEndsWith($label . "\n" . self::SOURCE_URL, $byChannel['8995754940207167599']['text']);
        $this->assertStringEndsWith($label . "\n" . self::SOURCE_URL, $byChannel['@saberblog']['text']);
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function createBot(): Bot
    {
        return Bot::create([
            'endpoint_id' => ChannelPosterBotController::ENDPOINT_ID,
            'bot_mother_id' => 1,
            'language_code' => 'fa',
            'bale_bot_token' => $this->token,
            'bale_bot_name' => 'channel_poster_bot',
            'bale_bot_status' => 'Active',
            'bale_owner_chat_id' => (int) $this->ownerChatId,
            'bale_webhook_is_set' => true,
        ]);
    }

    /**
     * دو کانال ایتا (مبدأ + ثانوی) + بله + تلگرام با تگ مشترک tag1.
     */
    private function createDestinations(int $botId): void
    {
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_3',
            'channel_title' => 'Alireza',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'eitaa',
            'channel_chat_id' => 'alirezamehran_4',
            'channel_title' => 'Alireza Four',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'bale',
            'channel_chat_id' => '8995754940207167599',
            'channel_title' => 'stackoverflow_com',
            'channel_username' => 'stackoverflow_com',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $botId,
            'platform' => 'telegram',
            'channel_chat_id' => '@saberblog',
            'channel_title' => 'Saber Blog',
            'tag' => 'tag1',
            'is_active' => true,
        ]);
    }

    private function postText(string $chatId, string $text): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token=' . $this->token,
            [
                'update_id' => rand(1, 999999),
                'message' => [
                    'message_id' => rand(1, 999999),
                    'from' => ['id' => (int) $chatId, 'is_bot' => false],
                    'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                    'date' => time(),
                    'text' => $text,
                ],
            ]
        )->assertOk();
    }

    private function postCallback(string $data): void
    {
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token=' . $this->token,
            [
                'update_id' => rand(1, 999999),
                'callback_query' => [
                    'id' => 'cb-' . md5($data),
                    'from' => ['id' => (int) $this->ownerChatId],
                    'data' => $data,
                    'message' => [
                        'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                    ],
                ],
            ]
        )->assertOk();
    }
}
