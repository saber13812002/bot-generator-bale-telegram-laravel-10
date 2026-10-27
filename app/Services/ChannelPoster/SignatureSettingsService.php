<?php

namespace App\Services\ChannelPoster;

use App\Models\ChannelPosterDestination;
use App\Models\ChannelPosterQueue;

/**
 * CP-SMART-SIGNATURE-SETTINGS — موتور سیاست امضای هوشمند.
 *
 * مدل سه‌بخشی تنظیمات امضا (به‌ازای هر مقصد/کانال):
 *
 *   A) سیاست انتخاب لینک (link_selection_policy):
 *      - self_only     : فقط لینک عمومی خود همان مقصد (سؤال چیدمان نمی‌آید)
 *      - all_channels  : همهٔ مقصدهای فعالِ تگ که لینک عمومی دارند (شامل خود)
 *      - exclude_self  : همهٔ مقصدهای فعالِ تگ با لینک عمومی، به‌جز خود
 *      - custom        : فهرست دستی مقصدها — همیشه با شناسهٔ مقصد (destination
 *                        id) نه نام پلتفرم (چند کانال یک‌پلتفرمی ممکن است)
 *      - random        : k مقصد تصادفی از مقصدهای واجد شرایط (تعداد را کاربر
 *                        تعیین می‌کند؛ پیش‌فرض سیستم self_only است و random
 *                        فقط با انتخاب صریح کاربر فعال می‌شود)
 *
 *   B) چیدمان (layout) — فقط وقتی بیش از یک لینک نهایی انتخاب شد سؤال می‌شود:
 *      - single_line   : همهٔ لینک‌ها در یک خط با جداکنندهٔ قابل‌تنظیم
 *                        (مثال: «ایتا | بله | سروش»)
 *      - multi_line    : هر لینک در یک خط مستقل
 *
 *   C) سیاست اعمال (application_policy):
 *      - always_apply  : پیش‌فرض — بدون سؤال هر بار
 *      - ask_every_post: یک سؤال کوتاه بله/خیر/تغییر تنظیمات در هر پست
 *      - disabled      : بدون امضا
 *
 * قواعد کلیدی:
 *  - انتخاب RANDOM فقط در لحظهٔ آماده‌سازی پیام (publish-time) انجام می‌شود و
 *    برای صف‌بانی در signature_selection فریز می‌شود تا retry/resend دقیقاً
 *    همان امضا را تولید کند (ثبات صف).
 *  - هیچ HTML/Markdown در تنظیمات ذخیره نمی‌شود؛ رندر نهایی در زمان انتشار
 *    توسط SignatureRenderer::renderMulti انجام می‌شود.
 *  - فقط لینک‌های عمومیِ ثبت‌شده (channel_link) استفاده می‌شوند؛ هرگز
 *    token/chat-id/لینک ساختگی.
 *  - اگر هیچ لینک معتبری باقی نماند، امضای ناقص ساخته نمی‌شود (خروجی خالی).
 *  - signature_settings = null → رفتار افسانه‌ای بدون تغییر (سازگاری معکوس).
 */
class SignatureSettingsService
{
    /** سیاست‌های انتخاب لینک */
    public const POLICY_SELF_ONLY = 'self_only';
    public const POLICY_ALL_CHANNELS = 'all_channels';
    public const POLICY_EXCLUDE_SELF = 'exclude_self';
    public const POLICY_CUSTOM = 'custom';
    public const POLICY_RANDOM = 'random';

    /** چیدمان‌ها */
    public const LAYOUT_SINGLE_LINE = 'single_line';
    public const LAYOUT_MULTI_LINE = 'multi_line';

    /** سیاست‌های اعمال */
    public const APPLY_ALWAYS = 'always_apply';
    public const APPLY_ASK_EVERY_POST = 'ask_every_post';
    public const APPLY_DISABLED = 'disabled';

    public const SEPARATOR_DEFAULT = ' | ';

    /**
     * پیش‌فرض کامل تنظیمات هوشمند (وقتی بخشی از JSON نگه‌داری نشده باشد).
     *
     * @return array{
     *   enabled: bool,
     *   link_selection_policy: string,
     *   layout: string,
     *   separator: string,
     *   selected_destinations: int[],
     *   random_count: int,
     *   application_policy: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'link_selection_policy' => self::POLICY_SELF_ONLY,
            'layout' => self::LAYOUT_SINGLE_LINE,
            'separator' => self::SEPARATOR_DEFAULT,
            'selected_destinations' => [],
            'random_count' => 2,
            'application_policy' => self::APPLY_ALWAYS,
        ];
    }

    /**
     * تنظیمات ذخیره‌شدهٔ یک مقصد را با پیش‌فرض‌ها ترکیب و اعتبارسنجی می‌کند.
     *
     * @param array|null $raw مقدار JSON ستون signature_settings (null = legacy)
     * @return array|null null اگر اصلا تنظیمات هوشمند ذخیره نشده باشد (legacy)
     */
    public static function normalize(?array $raw): ?array
    {
        if ($raw === null || !is_array($raw)) {
            return null;
        }

        $merged = array_merge(self::defaults(), array_filter($raw, fn ($v) => $v !== null));

        $policy = (string) ($merged['link_selection_policy'] ?? self::POLICY_SELF_ONLY);
        if (!in_array($policy, self::policies(), true)) {
            $merged['link_selection_policy'] = self::POLICY_SELF_ONLY;
        }

        $layout = (string) ($merged['layout'] ?? self::LAYOUT_SINGLE_LINE);
        if (!in_array($layout, self::layouts(), true)) {
            $merged['layout'] = self::LAYOUT_SINGLE_LINE;
        }

        $apply = (string) ($merged['application_policy'] ?? self::APPLY_ALWAYS);
        if (!in_array($apply, self::applicationPolicies(), true)) {
            $merged['application_policy'] = self::APPLY_ALWAYS;
        }

        // separator: برای single_line معنادار است؛ حداکثر 16 نویسه
        $sep = (string) ($merged['separator'] ?? self::SEPARATOR_DEFAULT);
        $merged['separator'] = $sep !== '' ? mb_substr($sep, 0, 16) : self::SEPARATOR_DEFAULT;

        // selected_destinations: فقط اعداد مثبت (destination id)
        $sel = $merged['selected_destinations'];
        $merged['selected_destinations'] = array_values(array_filter(
            is_array($sel) ? array_map('intval', $sel) : [],
            fn (int $id) => $id > 0
        ));

        $count = (int) ($merged['random_count'] ?? 2);
        $merged['random_count'] = max(1, min($count, 50));

        $merged['enabled'] = (bool) $merged['enabled'];

        return $merged;
    }

    /**
     * آیا مقصد از تنظیمات هوشمند (نه legacy) استفاده می‌کند؟
     */
    public static function isSmart(ChannelPosterDestination $dest): bool
    {
        $raw = $dest->signature_settings;
        if (!is_array($raw)) {
            return false;
        }

        $settings = self::normalize($raw);
        if ($settings === null) {
            return false;
        }

        // اگر کاربر «بدون امضا» را در سیاست اعمال انتخاب کرده باشد، تنظیمات
        // هوشمند فعال است ولی خروجی همیشه خالی است.
        if (!$settings['enabled'] && $settings['application_policy'] !== self::APPLY_DISABLED) {
            return false;
        }

        return true;
    }

    /**
     * آیا امضای این مقصد باید در زمان انتشار اعمال شود؟
     * (تلفیق ستون‌های semantic قدیمی + تنظیمات هوشمند جدید)
     */
    public static function isSignatureActive(ChannelPosterDestination $dest): bool
    {
        if ($dest->signature_enabled === false) {
            return false;
        }

        $settings = self::normalize(is_array($dest->signature_settings) ? $dest->signature_settings : null);
        if ($settings !== null) {
            return $settings['enabled']
                && $settings['application_policy'] !== self::APPLY_DISABLED;
        }

        // legacy: لینک ذخیره‌شده یعنی فعال
        return !empty($dest->channel_link);
    }

    /**
     * آیا سؤال کوتاهِ هر-بار لازم است؟ (ask_every_post)
     */
    public static function requiresPostQuestion(ChannelPosterDestination $dest): bool
    {
        $settings = self::normalize(is_array($dest->signature_settings) ? $dest->signature_settings : null);
        if ($settings === null) {
            return false;
        }

        return $settings['application_policy'] === self::APPLY_ASK_EVERY_POST
            && $settings['enabled']
            && !empty($dest->channel_link);
    }

    /**
     * آیا تگ دست‌کم یک مقصد با «سؤال هر بار» دارد؟ (برای مسیر انتشار)
     *
     * @param iterable<ChannelPosterDestination> $destinations
     */
    public static function anyRequiresPostQuestion(iterable $destinations): bool
    {
        foreach ($destinations as $destination) {
            if (self::requiresPostQuestion($destination)) {
                return true;
            }
        }

        return false;
    }

    /**
     * آیا حداقل یک لینک عمومی در تگ وجود دارد؟ (برای نمایش گزینه‌ی امضا)
     *
     * @param iterable<ChannelPosterDestination> $destinations
     */
    public static function anyHasLink(iterable $destinations): bool
    {
        foreach ($destinations as $destination) {
            if (!empty($destination->channel_link)) {
                return true;
            }
        }

        return false;
    }

    /**
     * سیاست اعمالِ مؤثر تگ: اگر همهٔ مقصدها legacy باشند → 'legacy'
     * (رفتار قدیمی: سؤال بله/خیر هر بار). در غیر این‌صورت سیاست اولین
     * مقصدِ هوشمند (تنظیمات مقصدها مستقل‌اند؛ در یک تگ معمولاً یک‌سان‌اند).
     */
    public static function effectiveApplicationPolicy(iterable $destinations): string
    {
        $smartPolicy = null;
        $hasSmart = false;
        $hasActiveLink = false;

        foreach ($destinations as $destination) {
            if (!empty($destination->channel_link)) {
                $hasActiveLink = true;
            }

            if (!self::isSmart($destination)) {
                continue;
            }

            $hasSmart = true;
            $settings = self::normalize($destination->signature_settings);
            if ($settings !== null && $smartPolicy === null) {
                $smartPolicy = $settings['application_policy'];
            }
        }

        if (!$hasSmart) {
            return $hasActiveLink ? 'legacy' : 'disabled';
        }

        if ($smartPolicy === self::APPLY_DISABLED) {
            return self::APPLY_DISABLED;
        }

        return $smartPolicy;
    }

    /**
     * مقصدهای واجد شرایط برای امضا (فعال + لینک عمومی).
     *
     * @param \Illuminate\Support\Collection<int, ChannelPosterDestination> $tagDestinations
     * @return \Illuminate\Support\Collection<int, ChannelPosterDestination>
     */
    public static function eligibleDestinations($tagDestinations)
    {
        return $tagDestinations
            ->filter(fn (ChannelPosterDestination $d) => !empty($d->channel_link))
            ->values();
    }

    /**
     * تعیین مقصدهایی که لینکشان در امضای $dest قرار می‌گیرد.
     *
     * @param ChannelPosterDestination $dest         مقصدی که امضایش ساخته می‌شود
     * @param \Illuminate\Support\Collection<int, ChannelPosterDestination> $tagDestinations همهٔ مقصدهای فعالِ تگ
     * @param array|null $frozenSelection            انتخاب فریزشده (از صف) برای ثبات retry
     * @return array<int, ChannelPosterDestination>  فهرست مقصدهای انتخاب‌شده (با order)
     */
    public static function resolveSelected(
        ChannelPosterDestination $dest,
        $tagDestinations,
        ?array $frozenSelection = null
    ): array {
        $settings = self::normalize(is_array($dest->signature_settings) ? $dest->signature_settings : null);
        if ($settings === null) {
            return [];
        }

        $eligible = self::eligibleDestinations($tagDestinations);
        $eligibleIds = $eligible->pluck('id')->all();

        if ($settings['link_selection_policy'] === self::POLICY_RANDOM && $frozenSelection !== null) {
            // انتخاب فریزشدهٔ صف — برای retry/resend دقیقاً همان امضا
            $ids = $frozenSelection[$dest->id] ?? ($frozenSelection[(string) $dest->id] ?? null);
            if ($ids !== null) {
                $selected = $eligible->filter(
                    fn (ChannelPosterDestination $d) => in_array($d->id, array_map('intval', (array) $ids), true)
                )->values()->all();
                if ($selected !== []) {
                    return $selected;
                }
                // اگر هیچ‌کدام از فریز‌شده‌ها دیگر واجد شرایط نباشند → self
                return [$dest];
            }
        }

        $policy = $settings['link_selection_policy'];
        if ($policy === self::POLICY_SELF_ONLY) {
            return !empty($dest->channel_link) ? [$dest] : [];
        }

        if ($policy === self::POLICY_ALL_CHANNELS) {
            return $eligible->all();
        }

        if ($policy === self::POLICY_EXCLUDE_SELF) {
            return $eligible
                ->filter(fn (ChannelPosterDestination $d) => $d->id !== $dest->id)
                ->values()
                ->all();
        }

        if ($policy === self::POLICY_CUSTOM) {
            $wanted = $settings['selected_destinations'];
            $selected = $eligible
                ->filter(fn (ChannelPosterDestination $d) => in_array($d->id, $wanted, true))
                ->values()
                ->all();
            // خود مقصد را هم اگر لینک دارد در نظر بگیریم (custom معمولاً صریح است
            // ولی حذف خود نباید امضای خالی بسازد)
            if ($selected === [] && !empty($dest->channel_link)) {
                $selected = [$dest];
            }
            return $selected;
        }

        if ($policy === self::POLICY_RANDOM) {
            return self::pickRandom($eligible, $settings['random_count'], $dest);
        }

        return !empty($dest->channel_link) ? [$dest] : [];
    }

    /**
     * انتخاب تصادفی — فقط در زمان انتشار؛ با seed قطعی‌ساز برای تست.
     *
     * @param \Illuminate\Support\Collection<int, ChannelPosterDestination> $eligible
     * @return array<int, ChannelPosterDestination>
     */
    public static function pickRandom($eligible, int $count, ChannelPosterDestination $dest, ?int $seed = null): array
    {
        $items = $eligible->values()->all();
        $count = max(1, min($count, count($items)));

        if ($count >= count($items)) {
            return $items;
        }

        $pool = array_keys($items);
        self::seededShuffle($pool, $seed);
        $chosen = array_slice($pool, 0, $count);

        $selected = [];
        foreach ($chosen as $id) {
            $selected[] = $items[$id];
        }

        // تضمین: خود مقصد (اگر لینک دارد) همیشه در امضا دیده شود؟ — خیر؛
        // سیاست random یعنی دقیقاً k مقصد تصادفی (مأموریت §3A).

        if ($selected === [] && !empty($dest->channel_link)) {
            return [$dest];
        }

        return $selected;
    }

    /**
     * shuffle با seed قطعی (LCG) — برای تست‌پذیری انتخاب random.
     */
    public static function seededShuffle(array &$items, ?int $seed = null): void
    {
        // محدودسازی seed به ۳۱ بیت: ضرب LCG در 1103515245 نباید overflow کند
        // (overflow → تبدیل به float → خطای cast در PHP 8).
        $seed = ($seed ?? (int) (microtime(true) * 1000)) & 0x7FFFFFFF;
        $n = count($items);
        for ($i = $n - 1; $i > 0; $i--) {
            $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $seed % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    /**
     * محاسبهٔ انتخاب فریزشدهٔ RANDOM برای همهٔ مقصدهای هوشمندِ تگ —
     * در لحظهٔ صف‌بانی/انتشار فوری ذخیره می‌شود (یک‌بار، نه در retry).
     *
     * @param \Illuminate\Support\Collection<int, ChannelPosterDestination> $tagDestinations
     * @return array<int, int[]> destination_id => [destination_id, ...]
     */
    public static function computeFrozenSelection(
        $tagDestinations,
        ?int $seed = null
    ): array {
        $frozen = [];
        $seed = $seed ?? random_int(1, 0x7FFFFFFF);

        foreach ($tagDestinations as $destination) {
            if (!self::isSmart($destination)) {
                continue;
            }
            $settings = self::normalize($destination->signature_settings);
            if ($settings === null || $settings['link_selection_policy'] !== self::POLICY_RANDOM) {
                continue;
            }
            $eligible = self::eligibleDestinations($tagDestinations);
            $selected = self::pickRandom($eligible, $settings['random_count'], $destination, $seed + $destination->id);
            $frozen[$destination->id] = array_map(fn (ChannelPosterDestination $d) => $d->id, $selected);
        }

        return $frozen;
    }

    /**
     * آیا سؤال چیدمان (layout) در ویزارد لازم است؟
     * فقط وقتی سیاست می‌تواند بیش از یک لینک نهایی بدهد:
     *  - self_only              → هرگز
     *  - custom                 → فقط اگر بیش از یک مقصد انتخاب شود
     *  - random                 → فقط اگر random_count > 1 باشد
     *  - all_channels/exclude_self → فقط اگر بیش از یک مقصد واجد شرایط باشد
     *
     * @param array{picks?: int[], random_count?: int}|null $customPicks برای custom:
     *                                                                    آرایهٔ destination idهای انتخابی؛
     *                                                                    برای random: ['random_count' => n]
     */
    public static function layoutQuestionNeeded(?string $policy, int $eligibleCount, ?array $customPicks = null): bool
    {
        $policy = $policy ?? self::POLICY_SELF_ONLY;

        if ($policy === self::POLICY_SELF_ONLY) {
            return false;
        }

        if ($policy === self::POLICY_CUSTOM) {
            $count = is_array($customPicks) ? count(array_filter($customPicks, fn ($v) => (int) $v > 0)) : 0;
            return $count > 1;
        }

        if ($policy === self::POLICY_RANDOM) {
            // random ممکن است 1+ لینک بدهد؛ سؤال چیدمان فقط وقتی count > 1
            // کاربر random با count=1 انتخاب کرده → سؤال نمی‌آید.
            return (int) ($customPicks['random_count'] ?? 0) > 1 ? true : false;
        }

        // all_channels / exclude_self: وقتی بیش از یک مقصد واجد شرایط است
        return $eligibleCount > 1;
    }

    /**
     * پیش‌نمایش متن سادهٔ امضا (بدون فرمت پلتفرم) — برای ویزارد/تنظیمات.
     *
     * @param array<int, ChannelPosterDestination> $selected
     */
    public static function buildPreview(array $selected, array $settings): string
    {
        $items = [];
        foreach ($selected as $d) {
            $label = !empty($d->signature_label)
                ? (string) $d->signature_label
                : (string) $d->channel_link;
            $items[] = $label;
        }

        if ($items === []) {
            return '';
        }

        if (($settings['layout'] ?? self::LAYOUT_SINGLE_LINE) === self::LAYOUT_SINGLE_LINE && count($items) > 1) {
            return implode((string) ($settings['separator'] ?? self::SEPARATOR_DEFAULT), $items);
        }

        return implode("\n", $items);
    }

    /**
     * ذخیرهٔ تنظیمات هوشمند یک مقصد (فقط دادهٔ معنایی — HTML/Markdown نه).
     */
    public static function saveForDestination(ChannelPosterDestination $dest, array $settings): ChannelPosterDestination
    {
        $normalized = self::normalize($settings);
        if ($normalized === null) {
            return $dest;
        }

        // اگر کاربر سیاست disabled انتخاب کرده، enabled=false هم نگه می‌داریم
        // تا هر دو لایهٔ semantic و هوشمند هماهنگ باشند.
        $enabled = $normalized['enabled']
            && $normalized['application_policy'] !== self::APPLY_DISABLED;

        $dest->signature_settings = $normalized;
        $dest->signature_enabled = $enabled;
        $dest->save();

        return $dest;
    }

    /**
     * پاک کردن تنظیمات هوشمند (بازگشت به legacy) — برای «حذف تنظیمات».
     */
    public static function clearForDestination(ChannelPosterDestination $dest): ChannelPosterDestination
    {
        $dest->signature_settings = null;
        // signature_enabled را null می‌کنیم تا legacy «لینک = فعال» حاکم شود
        $dest->signature_enabled = null;
        $dest->save();

        return $dest;
    }

    /**
     * انتخاب فریزشدهٔ یک آیتم صف را ذخیره می‌کند (ثبات retry).
     */
    public static function saveFrozenSelection(ChannelPosterQueue $item, array $frozen): void
    {
        if ($frozen === []) {
            return;
        }
        $item->signature_selection = $frozen;
        $item->save();
    }

    public static function policies(): array
    {
        return [
            self::POLICY_SELF_ONLY,
            self::POLICY_ALL_CHANNELS,
            self::POLICY_EXCLUDE_SELF,
            self::POLICY_CUSTOM,
            self::POLICY_RANDOM,
        ];
    }

    public static function layouts(): array
    {
        return [self::LAYOUT_SINGLE_LINE, self::LAYOUT_MULTI_LINE];
    }

    public static function applicationPolicies(): array
    {
        return [self::APPLY_ALWAYS, self::APPLY_ASK_EVERY_POST, self::APPLY_DISABLED];
    }
}
