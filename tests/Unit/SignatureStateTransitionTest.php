<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\ChannelPosterDestination;
use App\Services\ChannelPoster\SignatureRenderer;
use App\Services\ChannelPoster\SignatureSettingsService;
use App\Services\ChannelPosterBotServiceImpl;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-RELEASE-GATE — signature state-transition matrix (service level, real DB).
 *
 * Covers the release-gate behaviors that the existing suites do not:
 *   1. pure_url  -> handle   transition (stale label/URL must not render)
 *   2. handle    -> smart    transition (stale handle type must not render)
 *   3. smart     -> pure_url transition (stale settings must not render)
 *   4. disable-all signatures (the only path to signature_enabled = false)
 *   5. deletePureUrlSignature type-aware matrix (pure/handle/inline/composite/none/smart)
 *   6. cross-destination isolation after mixed transitions (no label/type leak)
 *
 * Every assertion is byte-exact (assertSame on the rendered string).
 */
class SignatureStateTransitionTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private const TG_URL = 'https://t.me/saberblog/42';
    private const TG_NEWS = 'https://t.me/sabernews/7';
    private const EITAA_URL = 'https://eitaa' . 'link.example/5';
    private const BALE_URL = 'https://balelink.example/3';

    private ChannelPosterBotServiceImpl $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
        $this->service = new ChannelPosterBotServiceImpl();
    }

    // ─────────────────────────────────────────────────────────────────────
    // T1 — pure_url -> handle
    // ─────────────────────────────────────────────────────────────────────

    public function test_pure_url_to_handle_transition(): void
    {
        $bot = $this->makeBot();
        $a = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_PURE_URL,
            'signature_label' => 'متن خداحافظی',
            'signature_enabled' => true,
        ]);
        $b = $this->makeDest($bot->id, 'eitaa', 2, [
            'channel_link' => self::EITAA_URL,
        ]);

        // before the transition
        $this->assertSame(
            "\n\nمتن خداحافظی\n" . self::TG_URL,
            $this->sig($a)['text']
        );
        $bBefore = $this->sig($b);
        $this->assertSame(
            "\n\nدر ایتا:\n" . self::EITAA_URL,
            $bBefore['text']
        );

        // write path: exactly what the handle wizard (saveHandleSignature) writes
        $this->service->updateSignatureSettings(
            $a->id,
            self::TG_URL,
            null,
            SignatureRenderer::TYPE_HANDLE,
            true
        );

        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $aF->signature_type);
        $this->assertNull($aF->signature_label, 'handle wizard stores null label for the default emoji');
        $this->assertTrue((bool) $aF->signature_enabled);
        $this->assertNull($aF->signature_settings);
        $this->assertSame(self::TG_URL, $aF->channel_link);

        // after: handle rendered from the destination's own data only
        $aAfter = $this->sig($aF);
        $this->assertSame("\n\n🔰 @saberblog", $aAfter['text']);
        $this->assertNull($aAfter['parse_mode']);
        $this->assertStringNotContainsString('متن خداحافظی', $aAfter['text'], 'stale pure_url text must not render');
        $this->assertStringNotContainsString(self::TG_URL, $aAfter['text'], 'stale pure_url URL must not render');

        // tagmate untouched (byte-identical)
        $this->assertSame($bBefore, $this->sig($b->fresh()));
    }

    // ─────────────────────────────────────────────────────────────────────
    // T2 — handle -> smart
    // ─────────────────────────────────────────────────────────────────────

    public function test_handle_to_smart_transition(): void
    {
        $bot = $this->makeBot();
        $a = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_NEWS,
            'signature_type' => SignatureRenderer::TYPE_HANDLE,
            'signature_label' => '⭐',
            'signature_enabled' => true,
        ]);
        $b = $this->makeDest($bot->id, 'eitaa', 2, [
            'channel_link' => self::EITAA_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_label' => 'برچسب بی',
        ]);

        $this->assertSame("\n\n⭐ @sabernews", $this->sig($a)['text']);
        $bBefore = $this->sig($b);
        $this->assertSame("\n\nبرچسب بی:\n" . self::EITAA_URL, $bBefore['text']);

        // write path: exactly what the smart wizard (saveSmartSignatureSettings) writes
        $this->service->saveSmartSignatureSettings($a->id, [
            'enabled' => true,
            'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
            'layout' => SignatureSettingsService::LAYOUT_MULTI_LINE,
        ]);

        $aF = $a->fresh();
        // the stale type/label remain by design — smart settings win at render time
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $aF->signature_type);
        $this->assertSame('⭐', $aF->signature_label);
        $this->assertTrue((bool) $aF->signature_enabled);
        $this->assertSame(
            SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
                'layout' => SignatureSettingsService::LAYOUT_MULTI_LINE,
            ]),
            SignatureSettingsService::normalize($aF->signature_settings)
        );

        // after: smart multi-line, platform priority order (eitaa first),
        // A's stale handle label never becomes a link label (type-aware)
        $aAfter = $this->sig($aF);
        $this->assertSame(
            "\n\nبرچسب بی:\n<a href=\"" . self::EITAA_URL . "\">برچسب بی</a>\nدر تلگرام:\n<a href=\"" . self::TG_NEWS . "\">در تلگرام</a>",
            $aAfter['text']
        );
        $this->assertSame('html', $aAfter['parse_mode']);
        $this->assertStringNotContainsString('⭐', $aAfter['text'], 'stale handle emoji must not render');
        $this->assertStringNotContainsString('@sabernews', $aAfter['text'], 'stale handle must not render');

        // tagmate untouched (byte-identical)
        $this->assertSame($bBefore, $this->sig($b->fresh()));
    }

    // ─────────────────────────────────────────────────────────────────────
    // T3 — smart -> pure_url
    // ─────────────────────────────────────────────────────────────────────

    public function test_smart_to_pure_url_transition(): void
    {
        $bot = $this->makeBot();
        $a = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_label' => 'برچسب هوشمند',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
                'layout' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
                'separator' => ' | ',
            ]),
            'signature_enabled' => true,
        ]);
        $b = $this->makeDest($bot->id, 'eitaa', 2, [
            'channel_link' => self::EITAA_URL,
        ]);

        // before: smart single_line (all_channels → platform priority order)
        $aBefore = $this->sig($a);
        $this->assertSame(
            "\n\n<a href=\"" . self::EITAA_URL . "\">در ایتا</a> | <a href=\"" . self::TG_URL . "\">برچسب هوشمند</a>",
            $aBefore['text']
        );
        $this->assertSame('html', $aBefore['parse_mode']);
        $bBefore = $this->sig($b);
        $this->assertSame("\n\nدر ایتا:\n" . self::EITAA_URL, $bBefore['text']);

        // write path: the pure-url wizard double-write
        // (updateSignatureSettings + null-out of the stale smart settings)
        $this->service->updateSignatureSettings(
            $a->id,
            self::TG_URL,
            'متن خداحافظی',
            SignatureRenderer::TYPE_PURE_URL,
            true
        );
        $tmp = $a->fresh();
        $tmp->signature_settings = null;
        $tmp->save();

        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_PURE_URL, $aF->signature_type);
        $this->assertSame('متن خداحافظی', $aF->signature_label);
        $this->assertNull($aF->signature_settings);
        $this->assertTrue((bool) $aF->signature_enabled);

        // after: plain pure_url output; no HTML, no stale label, no foreign URL
        $aAfter = $this->sig($aF);
        $this->assertSame("\n\nمتن خداحافظی\n" . self::TG_URL, $aAfter['text']);
        $this->assertNull($aAfter['parse_mode']);
        $this->assertStringNotContainsString('برچسب هوشمند', $aAfter['text']);
        $this->assertStringNotContainsString('<a', $aAfter['text']);
        $this->assertStringNotContainsString(self::EITAA_URL, $aAfter['text']);

        // tagmate untouched (byte-identical)
        $this->assertSame($bBefore, $this->sig($b->fresh()));
    }

    // ─────────────────────────────────────────────────────────────────────
    // T4 — disable-all signatures (every signature type)
    // ─────────────────────────────────────────────────────────────────────

    public function test_disable_all_signatures_kills_every_signature_type(): void
    {
        $cases = [
            // [platform, link, extra attrs, expected text before, expected parse mode before]
            [
                'telegram',
                self::TG_URL,
                [
                    'channel_username' => 'saberblog',
                    'signature_type' => SignatureRenderer::TYPE_PURE_URL,
                    'signature_label' => 'متن آ',
                    'signature_enabled' => true,
                ],
                "\n\nمتن آ\n" . self::TG_URL,
                null,
            ],
            [
                'telegram',
                self::TG_NEWS,
                [
                    'signature_type' => SignatureRenderer::TYPE_HANDLE,
                    'signature_label' => '⭐',
                    'signature_enabled' => true,
                ],
                "\n\n⭐ @sabernews",
                null,
            ],
            [
                'bale',
                self::BALE_URL,
                [
                    'signature_type' => SignatureRenderer::TYPE_INLINE,
                    'signature_settings' => SignatureSettingsService::normalize([
                        'enabled' => true,
                        'link_selection_policy' => SignatureSettingsService::POLICY_SELF_ONLY,
                        'layout' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
                    ]),
                    'signature_enabled' => true,
                ],
                "\n\n[در بله](" . self::BALE_URL . ")",
                null,
            ],
            [
                'eitaa',
                self::EITAA_URL,
                [
                    'signature_type' => SignatureRenderer::TYPE_INLINE,
                ],
                "\n\nدر ایتا:\n" . self::EITAA_URL,
                null,
            ],
        ];

        foreach ($cases as $i => [$platform, $link, $attrs, $beforeText, $beforeParseMode]) {
            $bot = $this->makeBot();
            $dest = $this->makeDest($bot->id, $platform, $i + 1, $attrs + [
                'channel_link' => $link,
            ]);

            $before = $this->sig($dest);
            $this->assertSame($beforeText, $before['text'], 'case ' . ($i + 1) . ': pre-state render');
            $this->assertSame($beforeParseMode, $before['parse_mode'], 'case ' . ($i + 1));

            $this->service->disableAllSignatures($dest->id);

            $f = $dest->fresh();
            $this->assertSame(false, $f->signature_enabled, 'case ' . ($i + 1) . ': strict boolean false');
            $this->assertSame(SignatureRenderer::TYPE_NONE, $f->signature_type);
            $this->assertNull($f->signature_settings);
            $this->assertNull($f->signature_label);
            $this->assertSame($link, $f->channel_link, 'case ' . ($i + 1) . ': channel_link preserved');

            $after = $this->sig($f);
            $this->assertSame('', $after['text'], 'case ' . ($i + 1) . ': no signature after disable-all');
            $this->assertNull($after['parse_mode']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // T5 — deletePureUrlSignature type-aware matrix
    // ─────────────────────────────────────────────────────────────────────

    public function test_delete_pure_url_signature_matrix_across_types(): void
    {
        // (a) pure_url → changed; only pure_url fields cleaned
        $bot = $this->makeBot();
        $a = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_PURE_URL,
            'signature_label' => 'متن آ',
            'signature_enabled' => true,
        ]);
        $res = $this->service->deletePureUrlSignature($a->id);
        $this->assertSame(['changed' => true, 'previous_type' => 'pure_url'], $res);
        $f = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_NONE, $f->signature_type);
        $this->assertNull($f->signature_label);
        $this->assertTrue((bool) $f->signature_enabled, 'enabled must stay untouched');
        $this->assertNull($f->signature_settings);
        $this->assertSame(self::TG_URL, $f->channel_link);
        $this->assertSame('', $this->sig($f)['text']);

        // (b) handle → no-op
        $bot = $this->makeBot();
        $b = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_NEWS,
            'signature_type' => SignatureRenderer::TYPE_HANDLE,
            'signature_label' => '⭐',
            'signature_enabled' => true,
        ]);
        $res = $this->service->deletePureUrlSignature($b->id);
        $this->assertSame(['changed' => false, 'previous_type' => 'handle'], $res);
        $this->assertSame("\n\n⭐ @sabernews", $this->sig($b->fresh())['text']);

        // (c) inline legacy → no-op
        $bot = $this->makeBot();
        $c = $this->makeDest($bot->id, 'eitaa', 1, [
            'channel_link' => self::EITAA_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_label' => 'برچسب',
        ]);
        $res = $this->service->deletePureUrlSignature($c->id);
        $this->assertSame(['changed' => false, 'previous_type' => 'inline'], $res);
        $this->assertSame("\n\nبرچسب:\n" . self::EITAA_URL, $this->sig($c->fresh())['text']);

        // (d) composite → no-op
        $bot = $this->makeBot();
        $d = $this->makeDest($bot->id, 'bale', 1, [
            'channel_link' => self::BALE_URL,
            'signature_type' => SignatureRenderer::TYPE_COMPOSITE,
            'signature_label' => 'برچسب',
        ]);
        $res = $this->service->deletePureUrlSignature($d->id);
        $this->assertSame(['changed' => false, 'previous_type' => 'composite'], $res);
        $this->assertSame(
            "\n\nبرچسب:\n[" . self::BALE_URL . '](' . self::BALE_URL . ')',
            $this->sig($d->fresh())['text']
        );

        // (e) none → no-op
        $bot = $this->makeBot();
        $e = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_NONE,
            'signature_enabled' => true,
        ]);
        $res = $this->service->deletePureUrlSignature($e->id);
        $this->assertSame(['changed' => false, 'previous_type' => 'none'], $res);
        $this->assertSame('', $this->sig($e->fresh())['text']);

        // (f) smart → no-op; settings JSON stays byte-identical
        $bot = $this->makeBot();
        $smartSettings = SignatureSettingsService::normalize([
            'enabled' => true,
            'link_selection_policy' => SignatureSettingsService::POLICY_SELF_ONLY,
            'layout' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
        ]);
        $f = $this->makeDest($bot->id, 'bale', 1, [
            'channel_link' => self::BALE_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_settings' => $smartSettings,
            'signature_enabled' => true,
        ]);
        $before = $this->sig($f);
        $this->assertSame("\n\n[در بله](" . self::BALE_URL . ")", $before['text']);
        $res = $this->service->deletePureUrlSignature($f->id);
        $this->assertSame(['changed' => false, 'previous_type' => 'inline'], $res);
        $this->assertSame(
            $smartSettings,
            SignatureSettingsService::normalize($f->fresh()->signature_settings),
            'smart settings must be untouched'
        );
        $this->assertSame($before['text'], $this->sig($f->fresh())['text']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // T6 — cross-destination isolation after mixed transitions
    // ─────────────────────────────────────────────────────────────────────

    public function test_cross_destination_isolation_after_mixed_transitions(): void
    {
        $bot = $this->makeBot();
        $a = $this->makeDest($bot->id, 'telegram', 1, [
            'channel_link' => self::TG_URL,
            'signature_type' => SignatureRenderer::TYPE_PURE_URL,
            'signature_label' => 'متن آ',
            'signature_enabled' => true,
        ]);
        $b = $this->makeDest($bot->id, 'telegram', 2, [
            'channel_link' => self::TG_NEWS,
            'signature_type' => SignatureRenderer::TYPE_HANDLE,
            'signature_label' => '⭐',
            'signature_enabled' => true,
        ]);
        $c = $this->makeDest($bot->id, 'eitaa', 3, [
            'channel_link' => self::EITAA_URL,
            'signature_type' => SignatureRenderer::TYPE_INLINE,
            'signature_label' => 'برچسب سی',
            'signature_settings' => SignatureSettingsService::normalize([
                'enabled' => true,
                'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
                'layout' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
            ]),
            'signature_enabled' => true,
        ]);

        // apply all three transitions
        $this->service->updateSignatureSettings($a->id, self::TG_URL, null, SignatureRenderer::TYPE_HANDLE, true);
        $this->service->saveSmartSignatureSettings($b->id, [
            'enabled' => true,
            'link_selection_policy' => SignatureSettingsService::POLICY_ALL_CHANNELS,
            'layout' => SignatureSettingsService::LAYOUT_MULTI_LINE,
        ]);
        $this->service->updateSignatureSettings($c->id, self::EITAA_URL, 'متن سی', SignatureRenderer::TYPE_PURE_URL, true);
        $cTmp = $c->fresh();
        $cTmp->signature_settings = null;
        $cTmp->save();

        // A: handle from its own data; no pure_url text/URL anywhere
        $aAfter = $this->sig($a->fresh());
        $this->assertSame("\n\n🔰 @saberblog", $aAfter['text']);
        $this->assertStringNotContainsString('متن آ', $aAfter['text']);
        $this->assertStringNotContainsString(self::TG_URL, $aAfter['text']);

        // B: smart multi-line. Order = platform priority (C first, then A, then B
        // — stable sort keeps DB order within the telegram priority bucket).
        // C's pure_url label and A/B handle emojis never appear as link labels.
        $bAfter = $this->sig($b->fresh());
        $this->assertSame(
            "\n\nدر ایتا:\n<a href=\"" . self::EITAA_URL . "\">در ایتا</a>\nدر تلگرام:\n<a href=\"" . self::TG_URL . "\">در تلگرام</a>\nدر تلگرام:\n<a href=\"" . self::TG_NEWS . "\">در تلگرام</a>",
            $bAfter['text']
        );
        $this->assertSame('html', $bAfter['parse_mode']);
        $this->assertStringNotContainsString('⭐', $bAfter['text']);
        $this->assertStringNotContainsString('@sabernews', $bAfter['text']);
        $this->assertStringNotContainsString('متن سی', $bAfter['text']);

        // C: pure_url; no smart label, no HTML, no telegram URLs
        $cAfter = $this->sig($c->fresh());
        $this->assertSame("\n\nمتن سی\n" . self::EITAA_URL, $cAfter['text']);
        $this->assertNull($cAfter['parse_mode']);
        $this->assertStringNotContainsString('برچسب سی', $cAfter['text']);
        $this->assertStringNotContainsString('<a', $cAfter['text']);
        $this->assertStringNotContainsString(self::TG_URL, $cAfter['text']);
        $this->assertStringNotContainsString(self::TG_NEWS, $cAfter['text']);

        // DB state of each destination is independent
        $aF = $a->fresh();
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $aF->signature_type);
        $this->assertNull($aF->signature_label);
        $this->assertNull($aF->signature_settings);
        $this->assertTrue((bool) $aF->signature_enabled);

        $bF = $b->fresh();
        $this->assertSame(SignatureRenderer::TYPE_HANDLE, $bF->signature_type, 'stale type remains by design');
        $this->assertTrue((bool) $bF->signature_enabled);
        $this->assertNotNull($bF->signature_settings);

        $cF = $c->fresh();
        $this->assertSame(SignatureRenderer::TYPE_PURE_URL, $cF->signature_type);
        $this->assertSame('متن سی', $cF->signature_label);
        $this->assertNull($cF->signature_settings);
        $this->assertTrue((bool) $cF->signature_enabled);
    }

    // ─────────────────────────────────────────────────────────────────────
    // helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeBot(): Bot
    {
        return Bot::create(['endpoint_id' => 'webhook-channel-poster', 'bale_bot_token' => '1:token']);
    }

    private function makeDest(int $botId, string $platform, int $n, array $attrs = []): ChannelPosterDestination
    {
        return ChannelPosterDestination::create(array_merge([
            'bot_id' => $botId,
            'platform' => $platform,
            'channel_chat_id' => 'st-' . $platform . $n,
            'channel_link' => 'https://' . $platform . 'link.example/' . $n,
            'is_active' => true,
            'tag' => 'tag1',
            'verified_at' => now(),
        ], $attrs));
    }

    private function sig(ChannelPosterDestination $dest): array
    {
        return $this->service->buildSignatureForDestination($dest->fresh());
    }
}
