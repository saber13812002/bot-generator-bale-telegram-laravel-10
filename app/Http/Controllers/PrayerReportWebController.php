<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\PrayerBotService;
use App\Models\BotUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PrayerReportWebController extends Controller
{
    protected PrayerBotService $prayerBotService;

    public function __construct(PrayerBotService $prayerBotService)
    {
        $this->prayerBotService = $prayerBotService;
    }

    /**
     * نمایش صفحه گزارش با token
     */
    public function show(string $token): View
    {
        Log::info('📊 [PrayerReportWeb] Accessing report page', ['token' => substr($token, 0, 10) . '...']);

        try {
            $user = BotUsers::where('web_report_token', $token)->first();

            if (!$user) {
                Log::warning('⚠️ [PrayerReportWeb] User not found', ['token' => substr($token, 0, 10) . '...']);
                abort(404, 'صفحه یافت نشد');
            }

            // دریافت گزارش هفتگی
            $rawReportData = $this->prayerBotService->getWeeklyReport($user->chat_id, $user->origin);
            
            // تبدیل به ساختار موردنیاز
            $reportData = $this->transformReportData($rawReportData);

            Log::info('✅ [PrayerReportWeb] Report data prepared', [
                'user_id' => $user->id,
                'chat_id' => $user->chat_id
            ]);

            return view('prayer-report-web', [
                'user' => $user,
                'reportData' => $reportData,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ [PrayerReportWeb] Error showing report', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'خطا در بارگذاری گزارش');
        }
    }

    /**
     * صفحه تست JavaScript
     */
    public function test(): View
    {
        return view('test-js');
    }

    /**
     * تبدیل reportData به ساختار موردنیاز برای Email
     */
    protected function transformReportData(array $rawReportData): array
    {
        $records = $rawReportData['records'] ?? collect();
        $statsByType = $rawReportData['stats_by_type'] ?? [];
        $progress = $rawReportData['progress'] ?? [];
        $period = $rawReportData['period'] ?? [];

        // محاسبه total_rakats از stats_by_type یا records
        $totalRakats = 0;
        foreach ($statsByType as $type => $stats) {
            $totalRakats += $stats['total_rakats'] ?? 0;
        }
        if ($totalRakats == 0) {
            $totalRakats = $records->sum('rakats');
        }

        // تبدیل stats_by_type به فرمت موردنیاز برای generateTextReport
        $prayersByType = [];
        foreach ($statsByType as $type => $stats) {
            $typeLabels = [
                'fajr' => 'صبح',
                'dhuhr' => 'ظهر',
                'asr' => 'عصر',
                'maghrib' => 'مغرب',
                'isha' => 'عشا',
            ];
            $label = $typeLabels[$type] ?? $type;
            $prayersByType[$label] = $stats['count'] ?? 0;
        }

        // اضافه کردن total_rakats به stats_by_type برای view ها
        $statsByTypeWithTotal = $statsByType;
        $statsByTypeWithTotal['total_rakats'] = $totalRakats;

        // تنظیم progress برای view ها
        $progressForView = $progress ? [
            'percentage' => $progress['progress_percentage'] ?? $progress['percentage'] ?? 0,
            'remaining' => $progress['remaining_rakats'] ?? $progress['remaining'] ?? 0,
        ] : null;

        // ساختار اصلی را حفظ می‌کنیم (برای view ها) و کلیدهای جدید را اضافه می‌کنیم (برای generateTextReport)
        return array_merge($rawReportData, [
            // کلیدهای جدید برای generateTextReport
            'period_start' => $period['from'] ?? now()->subWeek()->toDateString(),
            'period_end' => $period['to'] ?? now()->toDateString(),
            'total_prayers' => $records->count(),
            'total_rakats' => $totalRakats,
            'progress_percentage' => $progressForView['percentage'] ?? 0,
            'prayers_by_type' => $prayersByType,
            
            // به‌روزرسانی stats_by_type با total_rakats
            'stats_by_type' => $statsByTypeWithTotal,
            
            // به‌روزرسانی progress با کلیدهای درست
            'progress' => $progressForView,
            
            // اطلاعات تکمیلی (اگر وجود دارد)
            'daily_stats' => $rawReportData['daily_stats'] ?? [],
            'weekly_comparison' => $rawReportData['weekly_comparison'] ?? null,
            'peak_activity' => $rawReportData['peak_activity'] ?? null,
            'completion_time' => $rawReportData['completion_time'] ?? null,
            'top_10_users' => $rawReportData['top_10_users'] ?? [],
            'motivational_message' => $rawReportData['motivational_message'] ?? '',
        ]);
    }
}
