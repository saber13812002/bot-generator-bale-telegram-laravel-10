<?php

namespace App\Console\Commands;

use App\Interfaces\Services\EmailService;
use App\Interfaces\Services\PrayerBotService;
use App\Models\BotUsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendUserWeeklyReport extends Command
{
    protected $signature = 'email:send-user-report 
                            {--email= : Email address of the user}
                            {--user-id= : User ID}
                            {--preview : Only preview, do not send}';

    protected $description = 'ارسال گزارش هفتگی واقعی به کاربر و نمایش محتوای نهایی';

    protected PrayerBotService $prayerBotService;
    protected EmailService $emailService;

    public function __construct(PrayerBotService $prayerBotService, EmailService $emailService)
    {
        parent::__construct();
        $this->prayerBotService = $prayerBotService;
        $this->emailService = $emailService;
    }

    public function handle(): int
    {
        $email = $this->option('email');
        $userId = $this->option('user-id');
        $preview = $this->option('preview');

        $this->info('📧 ارسال گزارش هفتگی به کاربر');
        $this->newLine();

        // پیدا کردن کاربر
        $user = null;
        if ($userId) {
            $user = BotUsers::find($userId);
            if (!$user) {
                $this->error("❌ کاربر با ID {$userId} پیدا نشد");
                return 1;
            }
        } elseif ($email) {
            $user = BotUsers::where('email', $email)->first();
            if (!$user) {
                $this->error("❌ کاربر با ایمیل {$email} پیدا نشد");
                return 1;
            }
        } else {
            $this->error('❌ باید --email یا --user-id را مشخص کنید');
            return 1;
        }

        // بررسی اینکه ایمیل verify شده است
        if (!$user->email_verified_at) {
            $this->error("❌ ایمیل کاربر verify نشده است");
            return 1;
        }

        $this->info("👤 کاربر پیدا شد:");
        $this->info("   ID: {$user->id}");
        $this->info("   Chat ID: {$user->chat_id}");
        $this->info("   Email: {$user->email}");
        $this->info("   Origin: {$user->origin}");
        $this->newLine();

        try {
            // دریافت گزارش هفتگی
            $this->info('📊 دریافت گزارش هفتگی...');
            $rawReportData = $this->prayerBotService->getWeeklyReport($user->chat_id, $user->origin);
            
            // اضافه کردن URL گزارش وب
            if ($user->web_report_token) {
                $rawReportData['report_url'] = url("/namaz-ghaza/{$user->web_report_token}");
            }
            
            // تبدیل به ساختار موردنیاز
            $reportData = $this->transformReportData($rawReportData);
            
            $this->info('✅ گزارش دریافت شد');
            $this->info("   تعداد نمازها: {$reportData['total_prayers']}");
            $this->info("   تعداد رکعات: {$reportData['total_rakats']}");
            $this->info("   پیشرفت: {$reportData['progress_percentage']}%");
            $this->newLine();

            // نمایش تفکیک بر اساس نوع
            if (!empty($reportData['prayers_by_type'])) {
                $this->info("📋 تفکیک بر اساس نوع:");
                foreach ($reportData['prayers_by_type'] as $type => $count) {
                    $this->info("   • {$type}: {$count}");
                }
                $this->newLine();
            }

            // ایجاد توکن unsubscribe
            if (!$user->email_unsubscribe_token) {
                $user->email_unsubscribe_token = Str::random(64);
                $user->save();
            }

            // تولید محتوای HTML
            $this->info('📝 تولید محتوای ایمیل...');
            $templateVersion = rand(1, 5);
            $view = "emails.prayer-weekly-report-v{$templateVersion}";
            
            try {
                $htmlBody = view($view, [
                    'reportData' => $reportData,
                    'unsubscribeToken' => $user->email_unsubscribe_token,
                    'unsubscribeUrl' => url("/email/unsubscribe/{$user->email_unsubscribe_token}")
                ])->render();
                
                $this->info("✅ محتوای HTML تولید شد (تمپلیت: v{$templateVersion})");
            } catch (\Exception $e) {
                $this->error("❌ خطا در تولید HTML: {$e->getMessage()}");
                $htmlBody = "<html><body><h1>گزارش هفتگی</h1><p>گزارش هفتگی شما آماده است.</p></body></html>";
            }

            // تولید محتوای متنی
            $textBody = \App\Services\Email\EmailData::generateTextReport($reportData, $user->email_unsubscribe_token);

            // نمایش پیش‌نمایش
            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('📄 پیش‌نمایش محتوای متنی:');
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line($textBody);
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->newLine();

            // ذخیره HTML در فایل برای مشاهده
            $htmlFile = storage_path('app/temp_email_preview.html');
            file_put_contents($htmlFile, $htmlBody);
            $this->info("💾 محتوای HTML در فایل ذخیره شد: {$htmlFile}");
            $this->info("   می‌توانید این فایل را در مرورگر باز کنید");
            $this->newLine();

            if ($preview) {
                $this->info('✅ حالت Preview فعال است. ایمیل ارسال نشد.');
                return 0;
            }

            // ارسال ایمیل
            $this->info('📤 در حال ارسال ایمیل...');
            $this->emailService->sendWeeklyReportEmail(
                $user->email,
                $reportData,
                $user->email_unsubscribe_token,
                $templateVersion
            );

            $this->info("\n✅ ایمیل با موفقیت ارسال شد!");
            $this->info("📧 به: {$user->email}");
            $this->info("📋 تمپلیت: v{$templateVersion}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ خطا: {$e->getMessage()}");
            Log::error('❌ [SendUserWeeklyReport] Error', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
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
            
            // URL گزارش وب (اگر در rawReportData وجود دارد)
            'report_url' => $rawReportData['report_url'] ?? null,
        ]);
    }
}
