<?php

namespace App\Console\Commands;

use App\Models\AdminDailyChannelConfig;
use App\Models\BotHealthEvent;
use App\Models\ChannelPosterQueue;
use App\Services\AiProviderService;
use App\Services\ChannelPosterQueuePublishService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * نگهبان ساعتی صف ارسال کانال (channel-poster) — شبکه‌ی ایمنی
 *
 * هر ساعت:
 * 1) آیتم‌های صفی که موعدشان رسیده (status=pending و scheduled_at <= now - grace) را
 *    به‌صورت هم‌زمان از طریق سرویس مشترک publishItem دوباره ارسال می‌کند.
 *    (در صورتی که worker صف هر ۵ دقیقه در دسترس نباشد، این نگهبان ارسال را تضمین می‌کند.)
 * 2) اسلات‌های روزانه‌ی PostDailyVerseToChannels که باید اجرا شده‌اند را از روی ردِ
 *    BotHealthEvent (meta.config_id) بررسی می‌کند و اگر رده‌ای ثبت نشده، «از‌دست‌رفته» می‌شمارد.
 * 3) ماشین حالت هشدار (قرمز/سبز) را در کش به‌روزرسانی می‌کند:
 *    - قرمز: تا زمانی که مشکل وجود دارد، هر ساعت یک هشدار قرمز به ادمین‌ها (notifyAllAdmins).
 *    - سبز: فقط یک‌بار، در لحظه‌ی عبور از قرمز به سبز.
 *
 * کلیدهای کش:
 *   watchdog_state:bot:<bot_id>  = ok|red   (وضعیت هر بوت)
 *   watchdog_state:red_bots      = int[]    (فهرست بوت‌های قرمز برای تشخیص recovery)
 *   watchdog_state:red_daily     = bool     (وضعیت اسلات‌های روزانه)
 */
class PostOverdueWatchdog extends Command
{
    protected $signature = 'channel-poster:watchdog {--dry-run : فقط گزارش بده؛ ارسال مجدد و هشدار انجام نده}';

    protected $description = 'نگهبان ساعتی صف کانال: ارسال مجدد آیتم‌های عقب‌افتاده + بررسی اسلات‌های روزانه + هشدار قرمز/سبز';

    /** آیتمی که بیشتر از این دقیقه از موعدش گذشته، «عقب‌افتاده» محسوب می‌شود. */
    private const OVERDUE_GRACE_MINUTES = 15;

    /** فقط آیتم‌های حداکثر این روز اخیر در نظر گرفته می‌شوند (جلوگیری از هشدار برای آیتم‌های خیلی قدیمی). */
    private const MAX_AGE_DAYS = 7;

    /** TTL وضعیت کش نگهبان. */
    private const STATE_TTL = 60 * 60 * 48;

    private const STATE_OK  = 'ok';
    private const STATE_RED = 'red';

    private const RED_BOTS_KEY   = 'watchdog_state:red_bots';
    private const RED_DAILY_KEY  = 'watchdog_state:red_daily';
    private const BOT_STATE_PREF = 'watchdog_state:bot:';

    private const MAX_IDS_IN_ALERT = 10;
    private const MAX_DAILY_LINES  = 15;

    public function __construct(private ChannelPosterQueuePublishService $publishService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now    = now();
        $cutoff = $now->copy()->subMinutes(self::OVERDUE_GRACE_MINUTES);
        $floor  = $now->copy()->subDays(self::MAX_AGE_DAYS);

        $this->info('🐕 PostOverdueWatchdog شروع شد (cutoff: ' . $cutoff->format('Y-m-d H:i') . ')');

        // ── 1) ارسال مجدد آیتم‌های pendingِ عقب‌افتاده ───────────────────
        $resend = $this->resendOverduePending($cutoff, $floor, $dryRun);

        // ── 2) شناسایی بوت‌های قرمز (آیتم عقب‌افتاده‌ی non-published) ─────
        $redBots = $this->findRedBots($cutoff, $floor);

        // ── 3) بررسی اسلات‌های روزانه‌ی کانال ────────────────────────────
        $missedDaily = $this->findMissedDailySlots($now);
        $dailyRed    = count($missedDaily) > 0;

        // ── 4) ماشین حالت + هشدارها ──────────────────────────────────────
        $this->updateBotStatesAndAlert($redBots, $resend, $dryRun);
        $this->updateDailyStateAndAlert($dailyRed, $missedDaily, $dryRun);

        // ── خلاصه ─────────────────────────────────────────────────────────
        $this->newLine();
        $this->info(sprintf(
            '📊 نتیجه: %d بوت قرمز — %d اسلات روزانه از‌دست‌رفته — ارسال مجدد: %d موفق / %d ناموفق',
            count($redBots),
            count($missedDaily),
            $resend['published'],
            $resend['failed'],
        ));

        $anyRed = count($redBots) > 0 || $dailyRed;

        return $anyRed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * ارسال مجدد همه‌ی آیتم‌های pending که موعدشان (به‌علاوه‌ی grace) گذشته.
     *
     * @return array{published: int, failed: int, by_bot: array<int, array{published: int, failed: int}>}
     */
    private function resendOverduePending(Carbon $cutoff, Carbon $floor, bool $dryRun): array
    {
        $items = ChannelPosterQueue::query()
            ->pending()
            ->where('scheduled_at', '<=', $cutoff)
            ->where('scheduled_at', '>=', $floor)
            ->orderBy('scheduled_at')
            ->get();

        $published = 0;
        $failed    = 0;
        $byBot     = [];

        foreach ($items as $item) {
            $botId = (int) $item->bot_id;
            $byBot[$botId] ??= ['published' => 0, 'failed' => 0];

            if ($dryRun) {
                $this->line("  [DRY-RUN] resend #{$item->id} (bot {$botId}, due " . $item->scheduled_at->format('Y-m-d H:i') . ')');
                continue;
            }

            $result = $this->publishService->publishItem($item, false);

            if ($result['status'] === 'published') {
                $published++;
                $byBot[$botId]['published']++;
                $this->line("  ✅ resend #{$item->id} → published");
            } elseif ($result['status'] === 'failed') {
                $failed++;
                $byBot[$botId]['failed']++;
                $this->line("  ❌ resend #{$item->id} → failed: " . ($result['error'] ?? 'unknown'));
            } else {
                $this->line("  ⏭️  resend #{$item->id} → skipped");
            }
        }

        return ['published' => $published, 'failed' => $failed, 'by_bot' => $byBot];
    }

    /**
     * بوت‌هایی که حداقل یک آیتم non-published (pending یا failed) و عقب‌افتاده دارند.
     *
     * @return array<int, array{count: int, pending: int, failed: int, oldest: Carbon, ids: array<int, int>}>
     */
    private function findRedBots(Carbon $cutoff, Carbon $floor): array
    {
        $rows = ChannelPosterQueue::query()
            ->whereIn('status', [ChannelPosterQueue::STATUS_PENDING, ChannelPosterQueue::STATUS_FAILED])
            ->where('scheduled_at', '<=', $cutoff)
            ->where('scheduled_at', '>=', $floor)
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy('bot_id', true);

        $red = [];
        foreach ($rows as $botId => $group) {
            $ids = $group->pluck('id')->take(self::MAX_IDS_IN_ALERT)->values()->all();
            $red[(int) $botId] = [
                'count'   => $group->count(),
                'pending' => $group->where('status', ChannelPosterQueue::STATUS_PENDING)->count(),
                'failed'  => $group->where('status', ChannelPosterQueue::STATUS_FAILED)->count(),
                'oldest'  => $group->first()->scheduled_at,
                'ids'     => $ids,
            ];
        }

        return $red;
    }

    /**
     * اسلات‌های روزانه‌ای که باید اجرا شده‌اند اما رده‌ی سلامتی (channel_post) برای configشان ثبت نشده.
     *
     * @return array<int, string>
     */
    private function findMissedDailySlots(Carbon $now): array
    {
        $missed = [];

        for ($slot = 1; $slot <= 4; $slot++) {
            $slotTime     = $now->copy()->startOfDay()->addHours(($slot - 1) * 6);
            $nextSlotTime = $now->copy()->startOfDay()->addHours($slot * 6);

            // فقط اسلات‌هایی که موعدشان (به‌علاوه‌ی grace) از آن‌ها گذشته باشد
            if ($now->lt($slotTime->copy()->addMinutes(self::OVERDUE_GRACE_MINUTES))) {
                continue;
            }

            $configs = AdminDailyChannelConfig::query()
                ->where('is_active', true)
                ->get()
                ->filter(fn (AdminDailyChannelConfig $c) => $c->shouldRunInSlot($slot));

            if ($configs->isEmpty()) {
                continue;
            }

            $windowEnd = $nextSlotTime->lt($now) ? $nextSlotTime : $now;

            $events = BotHealthEvent::query()
                ->where('event_type', 'channel_post')
                ->where('created_at', '>=', $slotTime)
                ->where('created_at', '<', $windowEnd)
                ->get();

            foreach ($configs as $config) {
                $configEvents = $events->filter(function ($e) use ($config) {
                    $meta = is_array($e->meta) ? $e->meta : [];
                    return (int) ($meta['config_id'] ?? 0) === (int) $config->id;
                });

                if ($configEvents->isEmpty()) {
                    $missed[] = "config#{$config->id} اسلات {$slot} (" . $slotTime->format('H:i') . "): هیچ رده‌ای ثبت نشده";
                    continue;
                }

                if ($configEvents->where('status', 'ok')->isEmpty()) {
                    $missed[] = "config#{$config->id} اسلات {$slot} (" . $slotTime->format('H:i') . "): ارسال شد اما ناموفق";
                }
            }
        }

        return $missed;
    }

    /**
     * به‌روزرسانی وضعیت کش بوت‌ها + ارسال هشدار قرمز (هر ساعت) و سبز (فقط هنگام recovery).
     */
    private function updateBotStatesAndAlert(array $redBots, array $resend, bool $dryRun): void
    {
        $redNow  = array_keys($redBots);
        $prevRed = Cache::get(self::RED_BOTS_KEY, []);
        if (!is_array($prevRed)) {
            $prevRed = [];
        }
        $prevRed = array_map('intval', $prevRed);

        // ── قرمز: برای هر بوت قرمزِ فعلی ────────────────────────────────
        foreach ($redNow as $botId) {
            $this->sendBotRed($botId, $redBots[$botId], $resend['by_bot'][$botId] ?? ['published' => 0, 'failed' => 0], $dryRun);
            if (!$dryRun) {
                Cache::put(self::BOT_STATE_PREF . $botId, self::STATE_RED, self::STATE_TTL);
            }
        }

        // ── سبز: فقط برای بوت‌هایی که قبلاً قرمز بوده و حالا خوب شده ─────
        $recovered = array_values(array_diff($prevRed, $redNow));
        foreach ($recovered as $botId) {
            $this->sendBotGreen($botId, $dryRun);
            if (!$dryRun) {
                Cache::put(self::BOT_STATE_PREF . $botId, self::STATE_OK, self::STATE_TTL);
            }
        }

        if (!$dryRun) {
            Cache::put(self::RED_BOTS_KEY, array_values(array_map('intval', $redNow)), self::STATE_TTL);
        }
    }

    private function sendBotRed(int $botId, array $info, array $botResend, bool $dryRun): void
    {
        $oldest = $info['oldest']->format('Y-m-d H:i');
        $ids    = implode(', ', array_map(fn ($id) => "#{$id}", $info['ids']));
        $extra  = $info['count'] > count($info['ids']) ? ' (+' . ($info['count'] - count($info['ids'])) . ' دیگر)' : '';

        $msg = "🔴 Channel Poster OVERDUE\n"
            . "Bot: #{$botId}\n"
            . "Overdue items: {$info['count']} (pending: {$info['pending']}, failed: {$info['failed']})\n"
            . "Oldest due: {$oldest}\n"
            . "Items: {$ids}{$extra}\n"
            . "Watchdog resend this run: {$botResend['published']} ok / {$botResend['failed']} failed\n"
            . "Time: " . now()->format('Y-m-d H:i:s');

        if ($dryRun) {
            $this->warn("[DRY-RUN] would send RED alert for bot #{$botId}");
            return;
        }

        AiProviderService::notifyAllAdmins($msg);
        Log::warning('[PostOverdueWatchdog] RED', ['bot_id' => $botId, 'count' => $info['count']]);
        $this->warn("🔴 bot #{$botId}: {$info['count']} آیتم عقب‌افتاده — هشدار ارسال شد");
    }

    private function sendBotGreen(int $botId, bool $dryRun): void
    {
        $msg = "🟢 Channel Poster RECOVERED\n"
            . "Bot: #{$botId}\n"
            . "All overdue queue items are now published.\n"
            . "Time: " . now()->format('Y-m-d H:i:s');

        if ($dryRun) {
            $this->warn("[DRY-RUN] would send GREEN recovery for bot #{$botId}");
            return;
        }

        AiProviderService::notifyAllAdmins($msg);
        Log::info('[PostOverdueWatchdog] RECOVERED', ['bot_id' => $botId]);
        $this->info("🟢 bot #{$botId}: recovered — پیام ارسال شد");
    }

    /**
     * به‌روزرسانی وضعیت کش اسلات‌های روزانه + هشدار قرمز (هر ساعت) و سبز (فقط هنگام recovery).
     *
     * @param array<int, string> $missed
     */
    private function updateDailyStateAndAlert(bool $dailyRed, array $missed, bool $dryRun): void
    {
        $prevRed = (bool) Cache::get(self::RED_DAILY_KEY, false);

        if ($dailyRed) {
            $lines = implode("\n", array_map(fn ($m) => "  • {$m}", array_slice($missed, 0, self::MAX_DAILY_LINES)));
            $msg   = "🔴 Daily Channel Slots MISSED\n"
                . count($missed) . " slot post(s) not recorded:\n"
                . $lines . "\n"
                . "Time: " . now()->format('Y-m-d H:i:s');

            if ($dryRun) {
                $this->warn('[DRY-RUN] would send DAILY RED alert: ' . count($missed) . ' missed');
                return;
            }

            AiProviderService::notifyAllAdmins($msg);
            Cache::put(self::RED_DAILY_KEY, true, self::STATE_TTL);
            Log::warning('[PostOverdueWatchdog] DAILY RED', ['missed' => $missed]);
            $this->warn('🔴 daily slots: ' . count($missed) . ' — هشدار ارسال شد');

            return;
        }

        // سبز: فقط وقتی قبلاً قرمز بوده
        if ($prevRed) {
            $msg = "🟢 Daily Channel Slots RECOVERED\n"
                . "All expected slot posts are now recorded.\n"
                . "Time: " . now()->format('Y-m-d H:i:s');

            if (!$dryRun) {
                AiProviderService::notifyAllAdmins($msg);
                $this->info('🟢 daily slots: recovered — پیام ارسال شد');
            } else {
                $this->warn('[DRY-RUN] would send DAILY GREEN recovery');
            }
        }

        if (!$dryRun) {
            Cache::put(self::RED_DAILY_KEY, false, self::STATE_TTL);
        }
    }
}
