<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPosterBotServiceImpl;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

class ChannelPosterBotServiceTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private ChannelPosterBotServiceImpl $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        $this->service = new ChannelPosterBotServiceImpl();
    }

    public function test_owner_matches_bale_owner_chat_id(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-channel-poster',
            'bale_bot_token' => '1:token',
            'bale_owner_chat_id' => 111,
        ]);

        $this->assertTrue($this->service->isOwner($bot, '111', 'bale'));
        $this->assertFalse($this->service->isOwner($bot, '222', 'bale'));
        $this->assertFalse($this->service->isOwner($bot, '111', 'telegram'));
    }

    public function test_parse_channel_forward_accepts_bale_id_without_type(): void
    {
        $this->assertNull($this->service->parseChannelForward([
            'text' => 'hello',
        ]));

        $this->assertNull($this->service->parseChannelForward([
            'forward_from_chat' => [
                'id' => 111,
                'type' => 'private',
            ],
        ]));

        $withoutType = $this->service->parseChannelForward([
            'forward_from_chat' => [
                'id' => 234,
            ],
        ]);
        $this->assertSame('234', $withoutType['id']);

        $group = $this->service->parseChannelForward([
            'forward_from_chat' => [
                'id' => -1001,
                'type' => 'supergroup',
                'title' => 'Group',
            ],
        ]);
        $this->assertSame('-1001', $group['id']);

        $parsed = $this->service->parseChannelForward([
            'forward_from_chat' => [
                'id' => -100123,
                'type' => 'channel',
                'title' => 'News',
            ],
        ]);

        $this->assertSame('-100123', $parsed['id']);
        $this->assertSame('News', $parsed['title']);
        $this->assertSame('channel', $parsed['type']);
    }

    public function test_parse_channel_target_accepts_numeric_id(): void
    {
        $this->assertSame('-100555', $this->service->parseChannelTarget([
            'text' => '-100555',
        ])['id']);
        $this->assertNull($this->service->parseChannelTarget([
            'text' => 'hello',
        ]));
    }

    public function test_extract_media_types(): void
    {
        $this->assertSame('text', $this->service->extractMedia(['text' => 'hello'])['type']);
        $this->assertNull($this->service->extractMedia(['text' => '/start']));

        $photo = $this->service->extractMedia([
            'caption' => 'cap',
            'photo' => [
                ['file_id' => 'small'],
                ['file_id' => 'large'],
            ],
        ]);
        $this->assertSame('photo', $photo['type']);
        $this->assertSame('large', $photo['file_id']);
        $this->assertSame('cap', $photo['text']);

        $this->assertSame('voice', $this->service->extractMedia([
            'voice' => ['file_id' => 'v1'],
        ])['type']);
    }

    public function test_resolve_destinations_all_and_bale(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-channel-poster',
            'bale_bot_token' => '1:token',
        ]);

        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '-1001',
            'is_active' => true,
            'verified_at' => now(),
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'telegram',
            'channel_chat_id' => '-1002',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $baleOnly = $this->service->resolveDestinations($bot->id, 'bale');
        $this->assertCount(1, $baleOnly);
        $this->assertSame('bale', $baleOnly->first()->platform);

        $all = $this->service->resolveDestinations($bot->id, 'all');
        $this->assertCount(2, $all);
    }
}
