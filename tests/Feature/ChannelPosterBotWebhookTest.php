<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelPosterBotController;
use App\Interfaces\Services\ChannelPosterPublisherFactory;
use App\Models\Bot;
use App\Models\BotUserState;
use App\Models\BotUsers;
use App\Models\ChannelPosterDestination;
use Tests\Fakes\FakeChannelPosterPublisher;
use Tests\Fakes\FakeChannelPosterPublisherFactory;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class ChannelPosterBotWebhookTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private FakeChannelPosterPublisher $publisher;

    private string $token = '123:channel-poster-token';

    private string $ownerChatId = '111';

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

    public function test_start_by_non_owner_is_denied(): void
    {
        $this->createBot();

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate('222', '/start')
        );

        $response->assertOk();
        $this->assertStringContainsString(
            trans('bot.channel_poster_not_owner'),
            (string) $this->publisher->lastPrivateText()
        );
    }

    public function test_owner_start_without_destination_asks_for_forward(): void
    {
        $bot = $this->createBot();

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, '/start')
        );

        $response->assertOk();
        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_bale_forward'),
            (string) $this->publisher->lastPrivateText()
        );

        $botUser = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        $this->assertNotNull($botUser);
        $state = BotUserState::where('bot_user_id', $botUser->id)->first();
        $this->assertSame(ChannelPosterBotController::STATE_AWAITING_BALE_FORWARD, $state->state);
    }

    public function test_valid_channel_forward_saves_destination(): void
    {
        $bot = $this->createBot();
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, '/start')
        );

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateForwardUpdate($this->ownerChatId, -100555, 'channel', 'News')
        );

        $response->assertOk();
        $this->assertCount(1, $this->publisher->testMessages);
        $this->assertSame('-100555', $this->publisher->testMessages[0]['channel_chat_id']);
        $this->assertStringContainsString(
            trans('bot.channel_poster_bale_connected'),
            (string) $this->publisher->lastPrivateText()
        );

        $destination = ChannelPosterDestination::where('bot_id', $bot->id)->where('platform', 'bale')->first();
        $this->assertNotNull($destination);
        $this->assertSame('-100555', $destination->channel_chat_id);
        $this->assertSame('News', $destination->channel_title);

        $botUser = BotUsers::where('chat_id', $this->ownerChatId)->where('bot_id', $bot->id)->first();
        $this->assertSame(0, BotUserState::where('bot_user_id', $botUser->id)->count());
    }

    public function test_private_user_forward_is_rejected(): void
    {
        $this->createBot();
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, '/start')
        );

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateForwardUpdate($this->ownerChatId, 111, 'private', 'User')
        );

        $this->assertSame([], $this->publisher->testMessages);
        $this->assertStringContainsString(
            trans('bot.channel_poster_need_channel_forward'),
            (string) $this->publisher->lastPrivateText()
        );
        $this->assertSame(0, ChannelPosterDestination::count());
    }

    public function test_bale_forward_without_type_saves_destination(): void
    {
        $bot = $this->createBot();
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, '/start')
        );

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            [
                'update_id' => 4,
                'message' => [
                    'message_id' => 12,
                    'from' => ['id' => (int) $this->ownerChatId, 'is_bot' => false],
                    'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                    'date' => time(),
                    'text' => 'channel post',
                    'forward_from_chat' => [
                        'id' => 234,
                    ],
                ],
            ]
        );

        $response->assertOk();
        $this->assertSame('234', ChannelPosterDestination::where('bot_id', $bot->id)->value('channel_chat_id'));
    }

    public function test_numeric_chat_id_connects_bale_channel(): void
    {
        $bot = $this->createBot();
        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, '/start')
        );

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, '-100555')
        );

        $response->assertOk();
        $this->assertSame('-100555', ChannelPosterDestination::where('bot_id', $bot->id)->value('channel_chat_id'));
    }

    public function test_owner_content_then_bale_callback_publishes(): void
    {
        $bot = $this->createBot();
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '-100555',
            'channel_title' => 'News',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            $this->privateTextUpdate($this->ownerChatId, 'hello channel')
        );

        $this->assertStringContainsString(
            trans('bot.channel_poster_ask_destination'),
            (string) $this->publisher->lastPrivateText()
        );

        $response = $this->postJson(
            '/api/webhook-channel-poster?origin=bale&token='.$this->token,
            [
                'update_id' => 3,
                'callback_query' => [
                    'id' => 'cb1',
                    'from' => ['id' => (int) $this->ownerChatId],
                    'data' => 'cp:to:bale',
                    'message' => [
                        'chat' => ['id' => (int) $this->ownerChatId, 'type' => 'private'],
                    ],
                ],
            ]
        );

        $response->assertOk();
        $this->assertCount(1, $this->publisher->publishes);
        $this->assertSame('-100555', $this->publisher->publishes[0]['channel_chat_id']);
        $this->assertSame('text', $this->publisher->publishes[0]['content_type']);
        $this->assertSame('hello channel', $this->publisher->publishes[0]['text']);
        $this->assertStringContainsString(
            trans('bot.channel_poster_published'),
            (string) $this->publisher->lastPrivateText()
        );
    }

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

    private function privateTextUpdate(string $chatId, string $text): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'date' => time(),
                'text' => $text,
            ],
        ];
    }

    private function privateForwardUpdate(string $chatId, int $channelId, string $chatType, string $title): array
    {
        return [
            'update_id' => 2,
            'message' => [
                'message_id' => 11,
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'date' => time(),
                'text' => 'forwarded',
                'forward_from_chat' => [
                    'id' => $channelId,
                    'type' => $chatType,
                    'title' => $title,
                ],
            ],
        ];
    }
}
