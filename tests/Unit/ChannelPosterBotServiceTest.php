<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPoster\SignatureSettingsService;
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

    // ─────────── CP-SIGNATURE-LABEL-TYPE-AWARE (addendum C) ───────────

    private function makeDest(int $botId, string $platform, int $n, array $attrs = []): ChannelPosterDestination
    {
        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $botId,
            'platform' => $platform,
            'channel_chat_id' => 'sig-' . $platform . $n,
            'channel_link' => 'https://' . $platform . 'link.example/' . $n,
            'is_active' => true,
            'verified_at' => now(),
        ], $attrs));
    }

    public function test_smart_all_channels_does_not_leak_pure_url_label(): void
    {
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        $tg = $this->makeDest($bot->id, 'telegram', 1, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        $this->makeDest($bot->id, 'bale', 2, [
            'signature_label' => 'لینک خبرگزاری ما',
            'signature_type' => 'pure_url',
        ]);

        $sig = $this->service->buildSignatureForDestination($tg);
        $this->assertNotEmpty($sig['text']);
        $this->assertStringNotContainsString('لینک خبرگزاری ما', $sig['text']);
        // label لینک بله باید برچسب پلتفرم باشد (در تلگرام = HTML anchor «در بله»)
        $this->assertStringContainsString('در بله', $sig['text']);
    }

    public function test_smart_all_channels_does_not_leak_handle_emoji(): void
    {
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        $tg = $this->makeDest($bot->id, 'telegram', 1, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        $this->makeDest($bot->id, 'soroush', 2, [
            'signature_label' => '⭐',
            'signature_type' => 'handle',
            'channel_username' => 'sabernews',
        ]);

        $sig = $this->service->buildSignatureForDestination($tg);
        $this->assertNotEmpty($sig['text']);
        $this->assertStringNotContainsString('⭐', $sig['text']);
        $this->assertStringContainsString('در سروش', $sig['text']);
    }

    public function test_smart_all_channels_keeps_inline_and_legacy_labels(): void
    {
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        $tg = $this->makeDest($bot->id, 'telegram', 1, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        // inline: label کاربر = label لینک
        $this->makeDest($bot->id, 'bale', 2, ['signature_label' => 'خبری', 'signature_type' => 'inline']);
        // legacy (type خالی): label کاربر = label لینک (سازگاری دادهٔ قدیمی)
        $this->makeDest($bot->id, 'soroush', 3, ['signature_label' => 'خبر', 'signature_type' => null]);
        // بدون label: label پلتفرم
        $this->makeDest($bot->id, 'rubika', 4, ['signature_type' => 'inline']);

        $sig = $this->service->buildSignatureForDestination($tg);
        $this->assertNotEmpty($sig['text']);
        $this->assertStringContainsString('خبری', $sig['text']);
        // رندر در تلگرام (HTML): label هر مقصد داخل anchor همان پلتفرمِ مقصدِ رندر
        $this->assertStringContainsString('<a href="https://soroushlink.example/3">خبر</a>', $sig['text']);
        $this->assertStringContainsString('<a href="https://rubikalink.example/4">در روبیکا</a>', $sig['text']);
    }

    public function test_legacy_paths_interpret_label_only_by_their_own_type(): void
    {
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);

        // pure_url legacy: label متنِ خودِ pure_url است (نه label لینک) — رندر متن
        $pure = $this->makeDest($bot->id, 'bale', 1, [
            'signature_label' => 'لینک خبرگزاری ما',
            'signature_type' => 'pure_url',
        ]);
        $sig = $this->service->buildSignatureForDestination($pure);
        $this->assertSame("\n\nلینک خبرگزاری ما\nhttps://balelink.example/1", $sig['text']);
        $this->assertNull($sig['parse_mode']);

        // handle legacy: label ایموجی است؛ خروجی بدون لینک و بدون برچسب پلتفرم
        $handle = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_label' => '⭐',
            'signature_type' => 'handle',
            'channel_username' => 'sabernews',
        ]);
        $sig = $this->service->buildSignatureForDestination($handle);
        $this->assertSame("\n\n⭐ @sabernews", $sig['text']);
        $this->assertNull($sig['parse_mode']);

        // none legacy: امضای خالی
        $none = $this->makeDest($bot->id, 'telegram', 3, ['signature_type' => 'none']);
        $this->assertSame('', $this->service->buildSignatureForDestination($none)['text']);

        // inline legacy: label کاربر در خط مستقل + anchor (فرمت legacy: «برچسب:\nلینک»)
        $inline = $this->makeDest($bot->id, 'telegram', 4, ['signature_label' => 'خبری', 'signature_type' => 'inline']);
        $sig = $this->service->buildSignatureForDestination($inline);
        $this->assertSame(
            "\n\nخبری:\n<a href=\"https://telegramlink.example/4\">https://telegramlink.example/4</a>",
            $sig['text']
        );
        $this->assertSame('html', $sig['parse_mode']);

        // inline legacy بدون label کاربر → برچسب پلتفرم (رفتار legacy موجود)
        $plain = $this->makeDest($bot->id, 'telegram', 5, ['signature_type' => 'inline']);
        $sig = $this->service->buildSignatureForDestination($plain);
        $this->assertSame(
            "\n\nدر تلگرام:\n<a href=\"https://telegramlink.example/5\">https://telegramlink.example/5</a>",
            $sig['text']
        );
    }

    public function test_smart_all_channels_uses_default_platform_order(): void
    {
        // addendum D: ترتیب پیش‌فرض = Eitaa→Bale→Soroush→Telegram→Rubika
        // (مستقل از ترتیب درج در دیتابیس)
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        $rubika = $this->makeDest($bot->id, 'rubika', 1);
        $tg = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'all_channels',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        $bale = $this->makeDest($bot->id, 'bale', 3);
        $soroush = $this->makeDest($bot->id, 'soroush', 4);
        $eitaa = $this->makeDest($bot->id, 'eitaa', 5);

        $sig = $this->service->buildSignatureForDestination($tg);
        $this->assertSame(
            "\n\n"
            . '<a href="https://eitaa' . 'link.example/5">در ایتا</a>'
            . ' | <a href="https://balelink.example/3">در بله</a>'
            . ' | <a href="https://soroushlink.example/4">در سروش</a>'
            . ' | <a href="https://telegramlink.example/2">در تلگرام</a>'
            . ' | <a href="https://rubika' . 'link.example/1">در روبیکا</a>',
            $sig['text']
        );
    }

    public function test_smart_exclude_self_uses_default_platform_order(): void
    {
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        // in insertion order: rubika, telegram, soroush(dest), bale, eitaa
        $this->makeDest($bot->id, 'rubika', 1);
        $this->makeDest($bot->id, 'telegram', 2);
        $dest = $this->makeDest($bot->id, 'soroush', 3, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'exclude_self',
                'layout' => 'single_line',
                'separator' => ' | ',
            ]),
        ]);
        $this->makeDest($bot->id, 'bale', 4);
        $this->makeDest($bot->id, 'eitaa', 5);

        $sig = $this->service->buildSignatureForDestination($dest);
        // soroush renders plain text (no HTML) in platform priority order
        $this->assertSame(
            "\n\n"
            . 'در ایتا: https://eitaa' . 'link.example/5'
            . ' | در بله: https://balelink.example/4'
            . ' | در تلگرام: https://telegramlink.example/2'
            . ' | در روبیکا: https://rubika' . 'link.example/1',
            $sig['text']
        );
    }

    public function test_smart_custom_policy_keeps_user_pick_order(): void
    {
        // استثنای مصوب: custom → ترتیب انتخاب کاربر حفظ می‌شود
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        $bale = $this->makeDest($bot->id, 'bale', 1);
        $tg = $this->makeDest($bot->id, 'telegram', 2, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'custom',
                'layout' => 'single_line',
                'separator' => ' | ',
                'selected_destinations' => [$bale->id],
            ]),
        ]);
        $soroush = $this->makeDest($bot->id, 'soroush', 3);
        $rubika = $this->makeDest($bot->id, 'rubika', 4, [
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'custom',
                'layout' => 'single_line',
                'separator' => ' | ',
                'selected_destinations' => [],
            ]),
        ]);
        // custom picks: rubika first, then bale (reverse of platform priority)
        $rubika->update(['signature_settings' => SignatureSettingsService::normalize([
            'enabled' => true,
            'link_selection_policy' => 'custom',
            'layout' => 'single_line',
            'separator' => ' | ',
            'selected_destinations' => [$rubika->id, $bale->id],
        ])]);

        $sig = $this->service->buildSignatureForDestination($rubika);
        // rubika (user pick #1) must come BEFORE bale (user pick #2)
        $posRubika = mb_strpos($sig['text'], 'https://rubikalink.example/4');
        $posBale = mb_strpos($sig['text'], 'https://balelink.example/1');
        $this->assertNotFalse($posRubika);
        $this->assertNotFalse($posBale);
        $this->assertLessThan($posBale, $posRubika);
    }

    public function test_smart_random_policy_keeps_frozen_order(): void
    {
        // استثنای مصوب: random → ترتیب فریزشده دست‌نخورده می‌ماند
        $bot = Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
        $bale = $this->makeDest($bot->id, 'bale', 1);
        $rubika = $this->makeDest($bot->id, 'rubika', 2);
        $tg = $this->makeDest($bot->id, 'telegram', 3, [
            'signature_type' => 'inline',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => 'random',
                'layout' => 'single_line',
                'separator' => ' | ',
                'random_count' => 2,
            ]),
        ]);
        // eligible order = insertion: bale, rubika, telegram. Frozen picks
        // rubika + bale — the rendered order must NOT be re-sorted by
        // platform priority (bale before rubika) just because the policy
        // engine knows priorities.
        $frozen = [$tg->id => [$rubika->id, $bale->id]];

        $sig = $this->service->buildSignatureForDestination($tg, $frozen);
        $posRubika = mb_strpos($sig['text'], 'https://rubikalink.example/2');
        $posBale = mb_strpos($sig['text'], 'https://balelink.example/1');
        $this->assertNotFalse($posRubika);
        $this->assertNotFalse($posBale);
        // addendum D: the frozen id order [rubika, bale] is preserved —
        // NOT re-sorted by platform priority (bale would come first)
        $this->assertLessThan($posBale, $posRubika);
    }
}
