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

    public function test_parse_channel_forward_accepts_origin_chat_id(): void
    {
        $parsed = $this->service->parseChannelForward([
            'forward_origin' => [
                'type' => 'channel',
                'chat_id' => 4805465850,
            ],
        ]);
        $this->assertSame('4805465850', $parsed['id']);
    }

    public function test_parse_channel_forward_accepts_forward_from_without_name(): void
    {
        $parsed = $this->service->parseChannelForward([
            'forward_from' => [
                'id' => 234,
            ],
        ]);
        $this->assertSame('234', $parsed['id']);
    }

    public function test_claim_owner_if_empty(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-channel-poster',
            'bale_bot_token' => '1:token',
            'bale_owner_chat_id' => null,
        ]);

        $this->assertTrue($this->service->claimOwnerIfEmpty($bot, '5137394817', 'bale'));
        $this->assertSame('5137394817', (string) $bot->fresh()->bale_owner_chat_id);
        $this->assertFalse($this->service->claimOwnerIfEmpty($bot->fresh(), '999', 'bale'));
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

    /**
     * MP3 از طریق message.audio — نام و MIME اصلی باید حفظ شود.
     */
    public function test_extract_media_audio_key_preserves_name_and_mime(): void
    {
        $media = $this->service->extractMedia([
            'caption' => 'آهنگ تست',
            'audio' => [
                'file_id' => 'audio-id-1',
                'file_name' => 'song.mp3',
                'mime_type' => 'audio/mpeg',
            ],
        ]);

        $this->assertSame('audio', $media['type']);
        $this->assertSame('audio-id-1', $media['file_id']);
        $this->assertSame('آهنگ تست', $media['text']);
        $this->assertSame('song.mp3', $media['file_name']);
        $this->assertSame('audio/mpeg', $media['mime_type']);
    }

    /**
     * MP3 از طریق message.document با MIME معتبر audio/mpeg → نوع audio.
     */
    public function test_extract_media_document_mp3_with_mime_is_audio(): void
    {
        $media = $this->service->extractMedia([
            'document' => [
                'file_id' => 'doc-id-1',
                'file_name' => 'podcast.mp3',
                'mime_type' => 'audio/mpeg',
                'file_size' => 123456,
            ],
        ]);

        $this->assertSame('audio', $media['type']);
        $this->assertSame('doc-id-1', $media['file_id']);
        $this->assertSame('podcast.mp3', $media['file_name']);
        $this->assertSame('audio/mpeg', $media['mime_type']);
    }

    /**
     * MP3 از طریق document بدون MIME + پسوند معتبر .mp3 → همچنان audio
     * (اما فقط وقتی MIME به‌وضوح نوع دیگری نباشد).
     */
    public function test_extract_media_document_mp3_extension_without_mime_is_audio(): void
    {
        $media = $this->service->extractMedia([
            'document' => [
                'file_id' => 'doc-id-2',
                'file_name' => 'track.MP3',
            ],
        ]);

        $this->assertSame('audio', $media['type']);
        $this->assertSame('track.MP3', $media['file_name']);
        $this->assertNull($media['mime_type']);
    }

    /**
     * PDF از طریق document → نوع document با حفظ نام و MIME.
     */
    public function test_extract_media_document_pdf_is_document(): void
    {
        $media = $this->service->extractMedia([
            'caption' => 'مستندات',
            'document' => [
                'file_id' => 'doc-id-3',
                'file_name' => 'report.pdf',
                'mime_type' => 'application/pdf',
            ],
        ]);

        $this->assertSame('document', $media['type']);
        $this->assertSame('doc-id-3', $media['file_id']);
        $this->assertSame('report.pdf', $media['file_name']);
        $this->assertSame('application/pdf', $media['mime_type']);
        $this->assertSame('مستندات', $media['text']);
    }

    /**
     * نام کاذب .mp3 با MIME واضحِ غیرصوتی: به پسوند سمت کلاینت اعتماد نمی‌شود.
     */
    public function test_extract_media_document_fake_mp3_name_with_other_mime_not_audio(): void
    {
        $media = $this->service->extractMedia([
            'document' => [
                'file_id' => 'doc-id-4',
                'file_name' => 'fake.mp3',
                'mime_type' => 'image/png',
            ],
        ]);

        $this->assertSame('document', $media['type']);
    }

    /**
     * MP3 با کپشن → کپسن همراه نوع audio حفظ می‌شود.
     */
    public function test_extract_media_document_mp3_with_caption_keeps_caption(): void
    {
        $media = $this->service->extractMedia([
            'caption' => 'برنامه رادیویی',
            'document' => [
                'file_id' => 'doc-id-5',
                'file_name' => 'radio.mp3',
                'mime_type' => 'audio/mpeg',
            ],
        ]);

        $this->assertSame('audio', $media['type']);
        $this->assertSame('برنامه رادیویی', $media['text']);
        $this->assertSame('radio.mp3', $media['file_name']);
    }

    /**
     * صدای غیرMP3 (مثلا FLAC) از طریق document → audio_unsupported
     * (تا پیام خطای مشخص ارسال شود، نه رد بی‌صدا).
     */
    public function test_extract_media_document_unsupported_audio_is_flagged(): void
    {
        $media = $this->service->extractMedia([
            'document' => [
                'file_id' => 'doc-id-6',
                'file_name' => 'song.flac',
                'mime_type' => 'audio/flac',
            ],
        ]);

        $this->assertSame('audio_unsupported', $media['type']);
        $this->assertSame('doc-id-6', $media['file_id']);
        $this->assertSame('song.flac', $media['file_name']);
        $this->assertSame('audio/flac', $media['mime_type']);
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

    public function test_untagged_group_and_assign_tag(): void
    {
        $bot = Bot::create([
            'endpoint_id' => 'webhook-channel-poster',
            'bale_bot_token' => '1:token',
        ]);
        ChannelPosterDestination::create([
            'bot_id' => $bot->id,
            'platform' => 'bale',
            'channel_chat_id' => '4476004639',
            'channel_title' => 'شراب بهشتی',
            'is_active' => true,
        ]);

        $this->assertTrue($this->service->hasUntagged($bot->id));
        $groups = $this->service->listTagGroups($bot->id);
        $this->assertCount(1, $groups);
        $this->assertSame('', $groups[0]['key']);
        $this->assertSame('شراب بهشتی', $groups[0]['label']);

        $this->service->assignTagToUntagged($bot->id, 'شراب بهشتی');
        $this->assertFalse($this->service->hasUntagged($bot->id));
        $this->assertCount(1, $this->service->resolveByTag($bot->id, 'شراب بهشتی'));
    }
}
