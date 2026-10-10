<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\BotLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * گزارش فعالیت هفتگی ربات قرآن + تحلیل هوشمند (LLM)
 *
 * برای دستور /report:
 * - buildStats: آمار ۷ روزه‌ی کاربر از BotLog (آیات خوانده‌شده)
 * - buildCohort: مقایسه‌ی ناشناس با سایر کاربران (میانه/میانگین/صدک — بدون نام)
 * - analyze: تحلیل ۳ تا ۶ خطی فارسی از LLM؛ در صورت خرابی LLM برمی‌گردد null
 * - buildMessage: ترکیب همه‌چیز در یک پیام (با fallback ساده هنگام خرابی LLM)
 */
class ActivityReportSummaryService
{
    private const WEBHOOK_URI = 'webhook-quran-word';
    private const REGEX_AYA   = '/sure[0-9]+ayah[0-9]+/';
    private const MAX_TOKENS  = 300;

    /**
     * آمار ۷ روز اخیر کاربر.
     *
     * @return array{
     *   per_day: array<int, array{date: string, count: int}>,
     *   total_7d: int,
     *   total_prev_7d: int,
     *   change_percent: float,
     *   streak_days: int,
     *   avg_per_day: float,
     * }
     */
    public function buildStats(int $chatId, string $origin, ?string $language = null): array
    {
        $base = BotLog::query()
            ->where('chat_id', $chatId)
            ->where('webhook_endpoint_uri', self::WEBHOOK_URI)
            ->where('type', $origin)
            ->where('is_command', true)
            ->where('text', 'regexp', self::REGEX_AYA);

        if ($language) {
            $base->where('language', $language);
        }

        // ۷ روز اخیر — شمارش به تفکیک روز
        $perDay = [];
        $start = now()->subDays(6)->startOfDay();
        $end   = now()->endOfDay();
        $rows  = $base->clone()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn ($log) => Carbon::parse($log->created_at)->format('Y-m-d'));

        $total7d = 0;
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $rows->get($date)?->count() ?? 0;
            $total7d += $count;
            $perDay[] = ['date' => $date, 'count' => $count];
        }

        // هفته‌ی قبل (روزهای ۷ تا ۱۴ پیش)
        $prevStart = now()->subDays(14)->startOfDay();
        $prevEnd   = now()->subDays(7)->endOfDay();
        $totalPrev7d = (clone $base)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $change = $totalPrev7d > 0
            ? round((($total7d - $totalPrev7d) / $totalPrev7d) * 100, 1)
            : ($total7d > 0 ? 100.0 : 0.0);

        return [
            'per_day'       => $perDay,
            'total_7d'      => $total7d,
            'total_prev_7d' => $totalPrev7d,
            'change_percent'=> $change,
            'streak_days'   => $this->streakDays($chatId, $origin, $language),
            'avg_per_day'   => round($total7d / 7, 1),
        ];
    }

    /**
     * زنجیره‌ی روزهای فعال متوالی (از امروز به عقب).
     */
    private function streakDays(int $chatId, string $origin, ?string $language): int
    {
        try {
            $base = BotLog::query()
                ->where('chat_id', $chatId)
                ->where('webhook_endpoint_uri', self::WEBHOOK_URI)
                ->where('type', $origin)
                ->where('is_command', true)
                ->where('text', 'regexp', self::REGEX_AYA)
                ->where('created_at', '>=', now()->subDays(30)->startOfDay());

            if ($language) {
                $base->where('language', $language);
            }

            $days = $base->get(['created_at'])
                ->map(fn ($log) => Carbon::parse($log->created_at)->format('Y-m-d'))
                ->unique()
                ->values()
                ->all();

            // اگر امروز هنوز فعالیتی نبوده باشد، زنجیره را از دیروز ادامه می‌دهیم
            $cursor = now()->format('Y-m-d');
            if (!in_array($cursor, $days, true)) {
                $cursor = now()->subDay()->format('Y-m-d');
            }

            $streak = 0;
            while (in_array($cursor, $days, true)) {
                $streak++;
                $cursor = Carbon::parse($cursor)->subDay()->format('Y-m-d');
            }

            return $streak;
        } catch (Throwable $e) {
            Log::warning('[ActivityReport] streakDays failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * مقایسه‌ی ناشناس با سایر کاربران در همان ۷ روز.
     *
     * @return array{
     *   users: int,
     *   median: int,
     *   avg: float,
     *   max: int,
     *   percentile: int,
     *   has_user: bool,
     * }
     */
    public function buildCohort(int $chatId, string $origin, ?string $language = null): array
    {
        $empty = ['users' => 0, 'median' => 0, 'avg' => 0.0, 'max' => 0, 'percentile' => 0, 'has_user' => false];

        try {
            $base = BotLog::query()
                ->where('webhook_endpoint_uri', self::WEBHOOK_URI)
                ->where('type', $origin)
                ->where('is_command', true)
                ->where('text', 'regexp', self::REGEX_AYA)
                ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()]);

            if ($language) {
                $base->where('language', $language);
            }

            $totals = $base
                ->selectRaw('chat_id, COUNT(*) as c')
                ->groupBy('chat_id')
                ->get()
                ->map(fn ($row) => (int) $row->c)
                ->values();

            if ($totals->isEmpty()) {
                return $empty;
            }

            $sorted = $totals->sort()->values();
            $size   = $sorted->count();
            $mid    = intdiv($size, 2);
            $median = $size % 2 === 1
                ? $sorted[$mid]
                : (int) round(($sorted[$mid - 1] + $sorted[$mid]) / 2);

            $userTotal = (clone BotLog::query()
                ->where('chat_id', $chatId)
                ->where('webhook_endpoint_uri', self::WEBHOOK_URI)
                ->where('type', $origin)
                ->where('is_command', true)
                ->where('text', 'regexp', self::REGEX_AYA)
                ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()]))
                ->count();

            $atOrBelow = $totals->filter(fn ($c) => $c <= $userTotal)->count();

            return [
                'users'      => $size,
                'median'     => $median,
                'avg'        => round($totals->sum() / $size, 1),
                'max'        => $sorted->last(),
                'percentile' => (int) round($atOrBelow / $size * 100),
                'has_user'   => true,
            ];
        } catch (Throwable $e) {
            Log::warning('[ActivityReport] buildCohort failed', ['error' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * تحلیل فارسی ۳ تا ۶ خطی توسط LLM؛ در صورت هر خطا null.
     */
    public function analyze(array $stats, array $cohort): ?string
    {
        try {
            $provider = AiProvider::active()->orderBy('id')->first()
                ?? AiProviderService::ensureDefaultProvider();

            $result = (new AiProviderService($provider))->chat(
                $this->buildPrompt($stats, $cohort),
                self::MAX_TOKENS,
                0.4
            );

            if (!($result['success'] ?? false)) {
                return null;
            }

            $text = trim((string) ($result['response'] ?? ''));
            if ($text === '') {
                return null;
            }

            // حذف پیش‌وندهای احتمالی مدل
            foreach (['تحلیل:', 'تحلیل :', 'Analysis:'] as $prefix) {
                if (str_starts_with($text, $prefix)) {
                    $text = ltrim(substr($text, strlen($prefix)));
                }
            }

            $text = trim($text, "\"«» \n");
            return $text !== '' ? $text : null;
        } catch (Throwable $e) {
            Log::warning('[ActivityReport] LLM analyze failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildPrompt(array $stats, array $cohort): string
    {
        return "تو یک مشاور دلسوز و کوتاه‌حرف برای خوانندگان قرآن هستی.\n"
            . "آمار ۷ روز اخیر کاربر (آیات خوانده‌شده):\n"
            . "- مجموع ۷ روز: {$stats['total_7d']} آیه\n"
            . "- مجموع هفته‌ی قبل: {$stats['total_prev_7d']} آیه\n"
            . "- تغییر نسبت به هفته‌ی قبل: {$stats['change_percent']}٪\n"
            . "- میانگین روزانه: {$stats['avg_per_day']} آیه\n"
            . "- زنجیره‌ی فعالیت متوالی: {$stats['streak_days']} روز\n"
            . "مقایسه‌ی ناشناس با سایر کاربران (بدون نام و شناسه):\n"
            . "- تعداد کاربران فعال ۷ روز اخیر: {$cohort['users']} نفر\n"
            . "- میانه: {$cohort['median']} آیه | میانگین: {$cohort['avg']} آیه | بیشترین: {$cohort['max']} آیه\n"
            . "- این کاربر در صدکِ {$cohort['percentile']} قرار دارد (بالاتر از {$cohort['percentile']}٪ کاربران)\n\n"
            . "حالا یک تحلیل ۳ تا ۶ خطی، صمیمی و انگیزشی به فارسی بنویس که فعالیت این کاربر را با دیگران مقایسه کند. "
            . "از نام یا شناسه‌ی هیچ کاربری استفاده نکن. "
            . "اگر فعالیت کم بوده، مهربانانه و بدون سرزنش پیشنهاد بده. فقط متن تحلیل را بنویس.";
    }

    /**
     * پیام کامل /report: آمار + مقایسه + تحلیل (یا fallback ساده).
     */
    public function buildMessage(int $chatId, string $origin, ?string $language = null): string
    {
        $stats  = $this->buildStats($chatId, $origin, $language);
        $cohort = $this->buildCohort($chatId, $origin, $language);

        $msg  = "🤖 تحلیل هوشمند فعالیت قرآنی شما\n";
        $msg .= "─────────────────────\n";
        $msg .= "📊 ۷ روز اخیر: {$stats['total_7d']} آیه (میانگین روزانه {$stats['avg_per_day']})\n";
        $msg .= "📈 هفته‌ی قبل: {$stats['total_prev_7d']} آیه (تغییر {$stats['change_percent']}٪)\n";
        $msg .= "🔥 زنجیره‌ی فعالیت: {$stats['streak_days']} روز متوالی\n";

        if ($cohort['users'] > 0) {
            $msg .= "─────────────────────\n";
            $msg .= "👥 مقایسه با سایر کاربران (ناشناس):\n";
            $msg .= "   • کاربران فعال ۷ روز اخیر: {$cohort['users']} نفر\n";
            $msg .= "   • میانه: {$cohort['median']} | میانگین: {$cohort['avg']} | بیشترین: {$cohort['max']} آیه\n";
            $msg .= "   • شما بالاتر از {$cohort['percentile']}٪ کاربران هستید\n";
        }

        $analysis = $this->analyze($stats, $cohort);
        $msg .= "─────────────────────\n";
        $msg .= $analysis !== null
            ? "💬 تحلیل هوشمند:\n{$analysis}\n"
            : "💬 (تحلیل هوشمند در این لحظه در دسترس نیست — آمار بالا را ببینید و ادامه دهید 💪)";

        return $msg;
    }
}
