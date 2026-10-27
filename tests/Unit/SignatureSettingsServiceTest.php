<?php

namespace Tests\Unit;

use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;
use App\Services\ChannelPoster\SignatureSettingsService;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\UsesChannelPosterSqlite;

/**
 * CP-SMART-SIGNATURE-SETTINGS — unit tests for the signature policy engine.
 *
 * Covers: defaults/normalize clamping, smart/legacy detection, signature
 * activity, per-post question gating, eligible filtering, per-policy
 * selection (self_only / all_channels / exclude_self / custom / random),
 * frozen-selection stability, seeded-shuffle determinism + the large-seed
 * overflow regression, layout-question matrix, preview builder, and the
 * persistence helpers (save/clear/frozen).
 */
class SignatureSettingsServiceTest extends TestCase
{
    use UsesChannelPosterSqlite;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpChannelPosterTables();
    }

    // ───────────────────────────── helpers ─────────────────────────────

    private function dest(array $attrs = []): ChannelPosterDestination
    {
        $this->seq++;

        return ChannelPosterDestination::create(array_merge([
            'bot_id' => 1,
            'platform' => 'bale',
            'channel_chat_id' => 'chat-' . $this->seq,
            'channel_link' => 'https://ble.ir/c' . $this->seq,
        ], $attrs));
    }

    private function coll(ChannelPosterDestination ...$dests): Collection
    {
        return new Collection($dests);
    }

    private function smartSettings(array $overrides = []): array
    {
        return SignatureSettingsService::normalize(array_merge(
            SignatureSettingsService::defaults(),
            $overrides
        ));
    }

    // ───────────────────────── defaults / normalize ─────────────────────────

    public function test_defaults_shape(): void
    {
        $this->assertSame([
            'enabled' => true,
            'link_selection_policy' => SignatureSettingsService::POLICY_SELF_ONLY,
            'layout' => SignatureSettingsService::LAYOUT_SINGLE_LINE,
            'separator' => ' | ',
            'selected_destinations' => [],
            'random_count' => 2,
            'application_policy' => SignatureSettingsService::APPLY_ALWAYS,
        ], SignatureSettingsService::defaults());
    }

    public function test_normalize_null_is_legacy(): void
    {
        $this->assertNull(SignatureSettingsService::normalize(null));
    }

    public function test_normalize_clamps_and_rejects_invalid_values(): void
    {
        $norm = SignatureSettingsService::normalize([
            'enabled' => '1',
            'link_selection_policy' => 'bogus',
            'layout' => 'bogus',
            'application_policy' => 'bogus',
            'separator' => str_repeat('x', 20),
            'selected_destinations' => [5, 0, -3, '7', 'abc'],
            'random_count' => 100,
        ]);

        $this->assertTrue($norm['enabled']);
        $this->assertSame('self_only', $norm['link_selection_policy']);
        $this->assertSame('single_line', $norm['layout']);
        $this->assertSame('always_apply', $norm['application_policy']);
        $this->assertSame(str_repeat('x', 16), $norm['separator']);
        $this->assertSame([5, 7], $norm['selected_destinations']);
        $this->assertSame(50, $norm['random_count']);
    }

    public function test_normalize_empty_separator_and_zero_count_fall_back_to_defaults(): void
    {
        $norm = SignatureSettingsService::normalize(['separator' => '', 'random_count' => 0]);
        $this->assertSame(' | ', $norm['separator']);
        $this->assertSame(1, $norm['random_count']);
    }

    public function test_normalize_keeps_valid_partial_values(): void
    {
        $norm = SignatureSettingsService::normalize([
            'link_selection_policy' => 'custom',
            'layout' => 'multi_line',
            'separator' => '–',
            'selected_destinations' => [3],
            'random_count' => 3,
            'application_policy' => 'ask_every_post',
        ]);

        $this->assertSame('custom', $norm['link_selection_policy']);
        $this->assertSame('multi_line', $norm['layout']);
        $this->assertSame('–', $norm['separator']);
        $this->assertSame([3], $norm['selected_destinations']);
        $this->assertSame(3, $norm['random_count']);
        $this->assertSame('ask_every_post', $norm['application_policy']);
        $this->assertTrue($norm['enabled']);
    }

    // ───────────────────────── smart / active gating ─────────────────────────

    public function test_is_smart_requires_saved_settings(): void
    {
        $legacy = $this->dest();
        $this->assertFalse(SignatureSettingsService::isSmart($legacy));

        $smart = $this->dest(['signature_settings' => SignatureSettingsService::defaults()]);
        $this->assertTrue(SignatureSettingsService::isSmart($smart));

        // disabled application policy keeps the settings "smart" (output is
        // just always empty), so the wizard can still edit them.
        $disabledPolicy = $this->dest(['signature_settings' => $this->smartSettings([
            'enabled' => false,
            'application_policy' => 'disabled',
        ])]);
        $this->assertTrue(SignatureSettingsService::isSmart($disabledPolicy));

        // hard-off (enabled=false with a non-disabled policy) → legacy path.
        $hardOff = $this->dest(['signature_settings' => $this->smartSettings(['enabled' => false])]);
        $this->assertFalse(SignatureSettingsService::isSmart($hardOff));
    }

    public function test_is_signature_active_semantics(): void
    {
        $legacyLinked = $this->dest();
        $this->assertTrue(SignatureSettingsService::isSignatureActive($legacyLinked));

        $legacyNoLink = $this->dest(['channel_link' => null]);
        $this->assertFalse(SignatureSettingsService::isSignatureActive($legacyNoLink));

        $hardOff = $this->dest(['signature_enabled' => false]);
        $this->assertFalse(SignatureSettingsService::isSignatureActive($hardOff));

        $smart = $this->dest(['signature_settings' => SignatureSettingsService::defaults()]);
        $this->assertTrue(SignatureSettingsService::isSignatureActive($smart));

        $smartDisabled = $this->dest(['signature_settings' => $this->smartSettings([
            'application_policy' => 'disabled',
        ])]);
        $this->assertFalse(SignatureSettingsService::isSignatureActive($smartDisabled));
    }

    public function test_requires_post_question_only_for_ask_every_post(): void
    {
        $ask = $this->dest(['signature_settings' => $this->smartSettings([
            'application_policy' => 'ask_every_post',
        ])]);
        $this->assertTrue(SignatureSettingsService::requiresPostQuestion($ask));

        $askNoLink = $this->dest(['channel_link' => null, 'signature_settings' => $this->smartSettings([
            'application_policy' => 'ask_every_post',
        ])]);
        $this->assertFalse(SignatureSettingsService::requiresPostQuestion($askNoLink));

        $always = $this->dest(['signature_settings' => SignatureSettingsService::defaults()]);
        $this->assertFalse(SignatureSettingsService::requiresPostQuestion($always));

        $legacy = $this->dest();
        $this->assertFalse(SignatureSettingsService::requiresPostQuestion($legacy));
    }

    public function test_any_helpers(): void
    {
        $ask = $this->dest(['signature_settings' => $this->smartSettings([
            'application_policy' => 'ask_every_post',
        ])]);
        $plain = $this->dest();
        $noLink = $this->dest(['channel_link' => null]);

        $this->assertTrue(SignatureSettingsService::anyRequiresPostQuestion([$plain, $ask]));
        $this->assertFalse(SignatureSettingsService::anyRequiresPostQuestion([$plain, $noLink]));
        $this->assertTrue(SignatureSettingsService::anyHasLink([$noLink, $plain]));
        $this->assertFalse(SignatureSettingsService::anyHasLink([$noLink]));
    }

    public function test_effective_application_policy(): void
    {
        $legacyLinked = $this->dest();
        $this->assertSame('legacy', SignatureSettingsService::effectiveApplicationPolicy([$legacyLinked]));

        $legacyNoLink = $this->dest(['channel_link' => null]);
        $this->assertSame('disabled', SignatureSettingsService::effectiveApplicationPolicy([$legacyNoLink]));

        $ask = $this->dest(['signature_settings' => $this->smartSettings([
            'application_policy' => 'ask_every_post',
        ])]);
        $this->assertSame('ask_every_post', SignatureSettingsService::effectiveApplicationPolicy([$legacyLinked, $ask]));

        $dis = $this->dest(['signature_settings' => $this->smartSettings([
            'application_policy' => 'disabled',
        ])]);
        $this->assertSame('disabled', SignatureSettingsService::effectiveApplicationPolicy([$dis]));
    }

    // ───────────────────────────── selection ─────────────────────────────

    public function test_eligible_destinations_filters_missing_links(): void
    {
        $a = $this->dest();
        $b = $this->dest(['channel_link' => null]);
        $c = $this->dest(['channel_link' => '']);

        $eligible = SignatureSettingsService::eligibleDestinations($this->coll($a, $b, $c));
        $this->assertSame([$a->id], $eligible->pluck('id')->all());
    }

    public function test_resolve_selected_returns_empty_for_legacy_destination(): void
    {
        $a = $this->dest();
        $this->assertSame([], SignatureSettingsService::resolveSelected($a, $this->coll($a)));
    }

    public function test_resolve_selected_self_only(): void
    {
        $a = $this->dest(['signature_settings' => SignatureSettingsService::defaults()]);
        $b = $this->dest();
        $this->assertSame([$a], SignatureSettingsService::resolveSelected($a, $this->coll($a, $b)));

        $c = $this->dest(['channel_link' => null, 'signature_settings' => SignatureSettingsService::defaults()]);
        $this->assertSame([], SignatureSettingsService::resolveSelected($c, $this->coll($c, $b)));
    }

    public function test_resolve_selected_all_channels_returns_eligible_in_order(): void
    {
        $a = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'all_channels',
        ])]);
        $b = $this->dest();
        $c = $this->dest(['channel_link' => null]);
        $d = $this->dest();

        $this->assertSame(
            [$a->id, $b->id, $d->id],
            array_map(fn ($x) => $x->id, SignatureSettingsService::resolveSelected($a, $this->coll($a, $b, $c, $d)))
        );
    }

    public function test_resolve_selected_exclude_self_and_empty_result(): void
    {
        $a = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'exclude_self',
        ])]);
        $b = $this->dest();
        $c = $this->dest(['channel_link' => null]);

        $this->assertSame(
            [$b->id],
            array_map(fn ($x) => $x->id, SignatureSettingsService::resolveSelected($a, $this->coll($a, $b, $c)))
        );

        // only self is eligible → empty (NO partial signature is built)
        $only = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'exclude_self',
        ])]);
        $this->assertSame([], SignatureSettingsService::resolveSelected($only, $this->coll($only)));
    }

    public function test_resolve_selected_self_plus_eitaa_on_telegram_includes_self_plus_eligible_eitaa(): void
    {
        $tg = $this->dest(['platform' => 'telegram', 'signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
        ])]);
        $ei1 = $this->dest(['platform' => 'eitaa']);
        $ba = $this->dest();
        $so = $this->dest(['platform' => 'soroush']);
        $ei2 = $this->dest(['platform' => 'eitaa']);

        // telegram destination → self first, then ALL eligible eitaa in tag order
        $this->assertSame(
            [$tg->id, $ei1->id, $ei2->id],
            array_map(fn ($x) => $x->id, SignatureSettingsService::resolveSelected($tg, $this->coll($tg, $ei1, $ba, $so, $ei2)))
        );
    }

    public function test_resolve_selected_self_plus_eitaa_on_non_telegram_is_plain_self_only(): void
    {
        // eitaa destination → only self (the telegram link must NOT appear)
        $ei = $this->dest(['platform' => 'eitaa', 'signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
        ])]);
        $tg = $this->dest(['platform' => 'telegram']);
        $ba = $this->dest();
        $this->assertSame([$ei], SignatureSettingsService::resolveSelected($ei, $this->coll($ei, $tg, $ba)));

        // each other messenger → only its own link
        foreach (['bale', 'soroush', 'rubika'] as $platform) {
            $d = $this->dest(['platform' => $platform, 'signature_settings' => $this->smartSettings([
                'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
            ])]);
            $this->assertSame([$d], SignatureSettingsService::resolveSelected($d, $this->coll($d, $tg, $ei)));
        }
    }

    public function test_resolve_selected_self_plus_eitaa_never_fabricates_and_requires_self_link(): void
    {
        // telegram with no eligible eitaa → self only (no fabricated link)
        $tg = $this->dest(['platform' => 'telegram', 'signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
        ])]);
        $ba = $this->dest();
        $this->assertSame([$tg], SignatureSettingsService::resolveSelected($tg, $this->coll($tg, $ba)));

        // eitaa without a public link is never included
        $tg2 = $this->dest(['platform' => 'telegram', 'signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
        ])]);
        $eiNoLink = $this->dest(['platform' => 'eitaa', 'channel_link' => null]);
        $this->assertSame([$tg2], SignatureSettingsService::resolveSelected($tg2, $this->coll($tg2, $eiNoLink)));

        // telegram without its own link → empty (self link is required, no partial)
        $tg3 = $this->dest(['platform' => 'telegram', 'channel_link' => null, 'signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
        ])]);
        $ei = $this->dest(['platform' => 'eitaa']);
        $this->assertSame([], SignatureSettingsService::resolveSelected($tg3, $this->coll($tg3, $ei)));
    }

    public function test_resolve_selected_custom_respects_pick_ids_and_falls_back_to_self(): void
    {
        $a = $this->dest();
        $b = $this->dest();
        $c = $this->dest();
        $d = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'custom',
            'selected_destinations' => [$c->id, $b->id],
        ])]);

        // eligible order is preserved, not the pick order
        $this->assertSame(
            [$b->id, $c->id],
            array_map(fn ($x) => $x->id, SignatureSettingsService::resolveSelected($d, $this->coll($a, $b, $c, $d)))
        );

        // pick ids that match nothing → fall back to self (if linked)
        $e = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'custom',
            'selected_destinations' => [9999],
        ])]);
        $this->assertSame([$e], SignatureSettingsService::resolveSelected($e, $this->coll($a, $e)));
    }

    public function test_resolve_selected_random_live_and_frozen_paths(): void
    {
        $a = $this->dest();
        $b = $this->dest();
        $c = $this->dest();
        $d = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'random',
            'random_count' => 2,
        ])]);

        // live random (no frozen): exactly random_count items, all eligible
        $live = SignatureSettingsService::resolveSelected($d, $this->coll($a, $b, $c, $d));
        $this->assertCount(2, $live);
        foreach ($live as $x) {
            $this->assertTrue(in_array($x->id, [$a->id, $b->id, $c->id, $d->id], true));
        }

        // frozen selection (retry-stable): int keys
        $frozen = [$d->id => [$a->id, $c->id]];
        $sel = SignatureSettingsService::resolveSelected($d, $this->coll($a, $b, $c, $d), $frozen);
        $this->assertSame([$a->id, $c->id], array_map(fn ($x) => $x->id, $sel));

        // string-keyed frozen also accepted (JSON round-trip can stringify)
        $selStr = SignatureSettingsService::resolveSelected($d, $this->coll($a, $b, $c, $d), [(string) $d->id => [$b->id]]);
        $this->assertSame([$b->id], array_map(fn ($x) => $x->id, $selStr));

        // frozen ids all no longer eligible → self fallback
        $x = $this->dest();
        $y = $this->dest();
        $s = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'random',
            'random_count' => 2,
        ])]);
        $y->update(['channel_link' => null]);
        $selIneligible = SignatureSettingsService::resolveSelected($s, $this->coll($x, $y, $s), [$s->id => [$y->id]]);
        $this->assertSame([$s], $selIneligible);
    }

    public function test_pick_random_clamps_count_and_returns_all_when_count_ge_total(): void
    {
        $a = $this->dest();
        $b = $this->dest();

        $this->assertSame([$a, $b], SignatureSettingsService::pickRandom($this->coll($a, $b), 5, $a));

        $one = SignatureSettingsService::pickRandom($this->coll($a, $b), 0, $a);
        $this->assertCount(1, $one);
    }

    public function test_seeded_shuffle_is_deterministic_and_large_seed_is_safe(): void
    {
        $x = ['a', 'b', 'c', 'd', 'e', 'f'];
        $y = $x;
        SignatureSettingsService::seededShuffle($x, 42);
        SignatureSettingsService::seededShuffle($y, 42);
        $this->assertSame($x, $y);

        // Regression: the frozen-selection seed used to come from
        // random_int(1, PHP_INT_MAX); the LCG multiply then overflowed to a
        // float and crashed with a cast Error. Seeds must be masked to 31
        // bits, so PHP_INT_MAX and 0x7FFFFFFF behave identically.
        $u = ['a', 'b', 'c', 'd', 'e'];
        $v = ['a', 'b', 'c', 'd', 'e'];
        SignatureSettingsService::seededShuffle($u, PHP_INT_MAX);
        SignatureSettingsService::seededShuffle($v, 0x7FFFFFFF);
        $this->assertSame($u, $v);

        sort($u);
        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $u);
    }

    public function test_compute_frozen_selection_is_deterministic_and_skips_non_random(): void
    {
        $a = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'random', 'random_count' => 2,
        ])]);
        $b = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'random', 'random_count' => 2,
        ])]);
        $c = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'self_only',
        ])]);
        $d = $this->dest(); // legacy

        $coll = $this->coll($a, $b, $c, $d);
        $f1 = SignatureSettingsService::computeFrozenSelection($coll, 7);
        $f2 = SignatureSettingsService::computeFrozenSelection($coll, 7);
        $this->assertSame($f1, $f2);

        $this->assertArrayHasKey($a->id, $f1);
        $this->assertArrayHasKey($b->id, $f1);
        $this->assertArrayNotHasKey($c->id, $f1); // non-random smart dest
        $this->assertArrayNotHasKey($d->id, $f1); // legacy dest

        foreach ([$a->id, $b->id] as $id) {
            $this->assertCount(2, $f1[$id]);
            foreach ($f1[$id] as $selId) {
                $this->assertTrue(in_array($selId, [$a->id, $b->id, $c->id, $d->id], true));
            }
        }
    }

    public function test_compute_frozen_selection_without_seed_still_works(): void
    {
        $a = $this->dest(['signature_settings' => $this->smartSettings([
            'link_selection_policy' => 'random', 'random_count' => 2,
        ])]);
        $b = $this->dest();

        // Regression: the default seed path used random_int(1, PHP_INT_MAX)
        // and overflowed in the LCG multiply → cast Error swallowed by the
        // webhook try/catch (silent no-op). Now the seed is bounded.
        $frozen = SignatureSettingsService::computeFrozenSelection($this->coll($a, $b));
        $this->assertArrayHasKey($a->id, $frozen);
        $this->assertCount(2, $frozen[$a->id]);
    }

    // ─────────────────────────── wizard helpers ───────────────────────────

    public function test_layout_question_needed_matrix(): void
    {
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('self_only', 5));
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded(null, 5));

        $this->assertTrue(SignatureSettingsService::layoutQuestionNeeded('all_channels', 2));
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('all_channels', 1));
        $this->assertTrue(SignatureSettingsService::layoutQuestionNeeded('exclude_self', 3));

        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('custom', 4, [1]));
        $this->assertTrue(SignatureSettingsService::layoutQuestionNeeded('custom', 4, [1, 2]));
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('custom', 4, []));

        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('random', 5, ['random_count' => 1]));
        $this->assertTrue(SignatureSettingsService::layoutQuestionNeeded('random', 5, ['random_count' => 2]));
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('random', 5, null));

        // self_plus_eitaa: depends on the final link count (self + eligible eitaa),
        // not on the eligible count of the tag
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('self_only_plus_eitaa_on_telegram', 0, null, 1));
        $this->assertTrue(SignatureSettingsService::layoutQuestionNeeded('self_only_plus_eitaa_on_telegram', 5, null, 2));
        $this->assertFalse(SignatureSettingsService::layoutQuestionNeeded('self_only_plus_eitaa_on_telegram', 1, null, null));
    }

    public function test_build_preview_layouts_and_labels(): void
    {
        $a = $this->dest(['signature_label' => 'بله']);
        $b = $this->dest(); // no label → falls back to channel_link

        $single = $this->smartSettings(['layout' => 'single_line', 'separator' => '–']);
        $this->assertSame('بله–' . $b->channel_link, SignatureSettingsService::buildPreview([$a, $b], $single));

        $multi = $this->smartSettings(['layout' => 'multi_line']);
        $this->assertSame("بله\n" . $b->channel_link, SignatureSettingsService::buildPreview([$a, $b], $multi));

        // single item → no separator even in single_line; label wins over link
        $this->assertSame('بله', SignatureSettingsService::buildPreview([$a], $single));

        // label-less destination falls back to its channel_link
        $this->assertSame($b->channel_link, SignatureSettingsService::buildPreview([$b], $multi));

        $this->assertSame('', SignatureSettingsService::buildPreview([], $single));
    }

    public function test_policy_lists(): void
    {
        $this->assertSame(
            ['self_only', 'all_channels', 'exclude_self', 'self_only_plus_eitaa_on_telegram', 'custom', 'random'],
            SignatureSettingsService::policies()
        );
        $this->assertSame(['single_line', 'multi_line'], SignatureSettingsService::layouts());
        $this->assertSame(['always_apply', 'ask_every_post', 'disabled'], SignatureSettingsService::applicationPolicies());
    }

    public function test_normalize_keeps_new_preset_policy_and_rejects_garbage(): void
    {
        $kept = SignatureSettingsService::normalize([
            'enabled' => true,
            'link_selection_policy' => 'self_only_plus_eitaa_on_telegram',
        ]);
        $this->assertSame('self_only_plus_eitaa_on_telegram', $kept['link_selection_policy']);

        $rejected = SignatureSettingsService::normalize([
            'enabled' => true,
            'link_selection_policy' => 'self_only_plus_eitaa_typo',
        ]);
        $this->assertSame('self_only', $rejected['link_selection_policy']);
    }

    // ─────────────────────────── persistence ───────────────────────────

    public function test_save_for_destination_persists_normalized_and_syncs_enabled(): void
    {
        $a = $this->dest();
        SignatureSettingsService::saveForDestination($a, [
            'link_selection_policy' => 'custom',
            'selected_destinations' => [2, 0],
            'separator' => str_repeat('x', 30),
        ]);

        $fresh = $a->fresh();
        $this->assertSame([2], $fresh->signature_settings['selected_destinations']);
        $this->assertSame(str_repeat('x', 16), $fresh->signature_settings['separator']);
        $this->assertTrue($fresh->signature_enabled);

        SignatureSettingsService::saveForDestination($a, ['application_policy' => 'disabled']);
        $fresh = $a->fresh();
        $this->assertSame('disabled', $fresh->signature_settings['application_policy']);
        $this->assertFalse($fresh->signature_enabled);
    }

    public function test_clear_for_destination_returns_to_legacy(): void
    {
        $a = $this->dest(['signature_settings' => SignatureSettingsService::defaults(), 'signature_enabled' => true]);
        SignatureSettingsService::clearForDestination($a);

        $fresh = $a->fresh();
        $this->assertNull($fresh->signature_settings);
        $this->assertNull($fresh->signature_enabled);
        $this->assertFalse(SignatureSettingsService::isSmart($fresh));
        $this->assertTrue(SignatureSettingsService::isSignatureActive($fresh));
    }

    public function test_save_frozen_selection_persists_json_on_queue_item(): void
    {
        $item = ChannelPosterQueue::create([
            'bot_id' => 1,
            'tag' => 't1',
            'content_type' => 'text',
            'text' => 'hi',
            'scheduled_at' => '2026-01-01 12:00:00',
            'status' => 'pending',
        ]);
        $this->assertNull($item->signature_selection);

        // empty selection is a no-op (keeps the column null)
        SignatureSettingsService::saveFrozenSelection($item, []);
        $this->assertNull($item->fresh()->signature_selection);

        SignatureSettingsService::saveFrozenSelection($item, [7 => [1, 2]]);
        $this->assertSame([7 => [1, 2]], $item->fresh()->signature_selection);
    }
}
