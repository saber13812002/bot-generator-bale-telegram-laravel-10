<?php

namespace App\Console\Commands;

use App\Interfaces\Services\EmailService;
use App\Services\Email\EmailData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestWeatherEmails extends Command
{
    protected $signature = 'weather:test-emails 
                            {email : آدرس ایمیل برای ارسال تست}
                            {--type=daily : نوع ایمیل (daily, weekly, monthly, alert_temperature, alert_precipitation, alert_wind, alert_snow)}
                            {--lang=fa : زبان ایمیل (fa, en)}';

    protected $description = 'ارسال ایمیل تست هواشناسی در حالت‌های مختلف';

    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    public function handle(): int
    {
        $email = $this->argument('email');
        $type = $this->option('type');
        $lang = $this->option('lang');

        $this->info("📧 ارسال ایمیل تست هواشناسی به: {$email}");
        $this->info("📋 نوع: {$type}");
        $this->info("🌐 زبان: {$lang}");

        try {
            $emailData = $this->generateEmailData($email, $type, $lang);
            
            $result = $this->emailService->sendEmail($emailData);
            
            if ($result) {
                $this->info("✅ ایمیل با موفقیت ارسال شد!");
                return 0;
            } else {
                $this->error("❌ خطا در ارسال ایمیل");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ خطا: " . $e->getMessage());
            Log::error('❌ [TestWeatherEmails] Error', [
                'error' => $e->getMessage(),
                'type' => $type,
                'email' => $email
            ]);
            return 1;
        }
    }

    private function generateEmailData(string $email, string $type, string $lang): EmailData
    {
        app()->setLocale($lang);

        switch ($type) {
            case 'daily':
                return $this->generateDailyReport($email, $lang);
            
            case 'weekly':
                return $this->generateWeeklyReport($email, $lang);
            
            case 'monthly':
                return $this->generateMonthlyReport($email, $lang);
            
            case 'alert_temperature':
                return $this->generateAlertEmail($email, 'temperature', $lang);
            
            case 'alert_precipitation':
                return $this->generateAlertEmail($email, 'precipitation', $lang);
            
            case 'alert_wind':
                return $this->generateAlertEmail($email, 'wind', $lang);
            
            case 'alert_snow':
                return $this->generateAlertEmail($email, 'snow', $lang);
            
            default:
                throw new \Exception("نوع ایمیل نامعتبر: {$type}");
        }
    }

    private function generateDailyReport(string $email, string $lang): EmailData
    {
        $subject = $lang === 'fa' 
            ? '🌤️ گزارش روزانه هواشناسی'
            : '🌤️ Daily Weather Report';

        $htmlBody = $this->buildDailyReportHtml($lang);
        $textBody = $this->buildDailyReportText($lang);

        return new EmailData(
            to: $email,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            category: 'Weather Daily Report'
        );
    }

    private function generateWeeklyReport(string $email, string $lang): EmailData
    {
        $subject = $lang === 'fa'
            ? '📊 گزارش هفتگی هواشناسی'
            : '📊 Weekly Weather Report';

        $htmlBody = $this->buildWeeklyReportHtml($lang);
        $textBody = $this->buildWeeklyReportText($lang);

        return new EmailData(
            to: $email,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            category: 'Weather Weekly Report'
        );
    }

    private function generateMonthlyReport(string $email, string $lang): EmailData
    {
        $subject = $lang === 'fa'
            ? '📈 گزارش ماهانه هواشناسی'
            : '📈 Monthly Weather Report';

        $htmlBody = $this->buildMonthlyReportHtml($lang);
        $textBody = $this->buildMonthlyReportText($lang);

        return new EmailData(
            to: $email,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            category: 'Weather Monthly Report'
        );
    }

    private function generateAlertEmail(string $email, string $alertType, string $lang): EmailData
    {
        $alertTypes = [
            'temperature' => $lang === 'fa' ? 'دما' : 'Temperature',
            'precipitation' => $lang === 'fa' ? 'بارش' : 'Precipitation',
            'wind' => $lang === 'fa' ? 'باد' : 'Wind',
            'snow' => $lang === 'fa' ? 'برف' : 'Snow',
        ];

        $subject = $lang === 'fa'
            ? "🚨 هشدار هواشناسی: {$alertTypes[$alertType]}"
            : "🚨 Weather Alert: {$alertTypes[$alertType]}";

        $htmlBody = $this->buildAlertEmailHtml($alertType, $lang);
        $textBody = $this->buildAlertEmailText($alertType, $lang);

        return new EmailData(
            to: $email,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            category: 'Weather Alert'
        );
    }

    private function buildDailyReportHtml(string $lang): string
    {
        if ($lang === 'fa') {
            return "
            <!DOCTYPE html>
            <html dir='rtl' lang='fa'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .weather-item { margin: 15px 0; padding: 15px; background: white; border-right: 3px solid #4CAF50; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🌤️ گزارش روزانه هواشناسی</h2>
                    </div>
                    <div class='content'>
                        <div class='weather-item'>
                            <strong>📍 موقعیت:</strong> تهران، ایران<br>
                            <strong>📅 تاریخ:</strong> " . now()->format('Y-m-d') . "
                        </div>
                        <div class='weather-item'>
                            <strong>🌡️ دما:</strong> 15°C<br>
                            <strong>💨 سرعت باد:</strong> 12 km/h<br>
                            <strong>☁️ ابری:</strong> 30%<br>
                            <strong>💧 رطوبت:</strong> 45%
                        </div>
                        <div class='weather-item'>
                            <strong>📊 پیش‌بینی امروز:</strong><br>
                            صبح: آفتابی، 12°C<br>
                            ظهر: نیمه ابری، 18°C<br>
                            شب: ابری، 10°C
                        </div>
                    </div>
                    <div class='footer'>
                        این ایمیل به صورت خودکار ارسال شده است.
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            return "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .weather-item { margin: 15px 0; padding: 15px; background: white; border-left: 3px solid #4CAF50; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🌤️ Daily Weather Report</h2>
                    </div>
                    <div class='content'>
                        <div class='weather-item'>
                            <strong>📍 Location:</strong> Tehran, Iran<br>
                            <strong>📅 Date:</strong> " . now()->format('Y-m-d') . "
                        </div>
                        <div class='weather-item'>
                            <strong>🌡️ Temperature:</strong> 15°C<br>
                            <strong>💨 Wind Speed:</strong> 12 km/h<br>
                            <strong>☁️ Cloud Cover:</strong> 30%<br>
                            <strong>💧 Humidity:</strong> 45%
                        </div>
                        <div class='weather-item'>
                            <strong>📊 Today's Forecast:</strong><br>
                            Morning: Sunny, 12°C<br>
                            Noon: Partly Cloudy, 18°C<br>
                            Night: Cloudy, 10°C
                        </div>
                    </div>
                    <div class='footer'>
                        This email was sent automatically.
                    </div>
                </div>
            </body>
            </html>
            ";
        }
    }

    private function buildDailyReportText(string $lang): string
    {
        if ($lang === 'fa') {
            return "
🌤️ گزارش روزانه هواشناسی

📍 موقعیت: تهران، ایران
📅 تاریخ: " . now()->format('Y-m-d') . "

🌡️ دما: 15°C
💨 سرعت باد: 12 km/h
☁️ ابری: 30%
💧 رطوبت: 45%

📊 پیش‌بینی امروز:
صبح: آفتابی، 12°C
ظهر: نیمه ابری، 18°C
شب: ابری، 10°C
            ";
        } else {
            return "
🌤️ Daily Weather Report

📍 Location: Tehran, Iran
📅 Date: " . now()->format('Y-m-d') . "

🌡️ Temperature: 15°C
💨 Wind Speed: 12 km/h
☁️ Cloud Cover: 30%
💧 Humidity: 45%

📊 Today's Forecast:
Morning: Sunny, 12°C
Noon: Partly Cloudy, 18°C
Night: Cloudy, 10°C
            ";
        }
    }

    private function buildWeeklyReportHtml(string $lang): string
    {
        if ($lang === 'fa') {
            return "
            <!DOCTYPE html>
            <html dir='rtl' lang='fa'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #2196F3; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .day-item { margin: 10px 0; padding: 10px; background: white; border-right: 3px solid #2196F3; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📊 گزارش هفتگی هواشناسی</h2>
                    </div>
                    <div class='content'>
                        <div class='day-item'>
                            <strong>شنبه:</strong> آفتابی، 18°C / 8°C
                        </div>
                        <div class='day-item'>
                            <strong>یکشنبه:</strong> نیمه ابری، 16°C / 7°C
                        </div>
                        <div class='day-item'>
                            <strong>دوشنبه:</strong> ابری، 14°C / 6°C
                        </div>
                        <div class='day-item'>
                            <strong>سه‌شنبه:</strong> بارانی، 12°C / 5°C
                        </div>
                        <div class='day-item'>
                            <strong>چهارشنبه:</strong> آفتابی، 17°C / 9°C
                        </div>
                        <div class='day-item'>
                            <strong>پنج‌شنبه:</strong> نیمه ابری، 15°C / 7°C
                        </div>
                        <div class='day-item'>
                            <strong>جمعه:</strong> آفتابی، 19°C / 10°C
                        </div>
                    </div>
                    <div class='footer'>
                        این ایمیل به صورت خودکار ارسال شده است.
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            return "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #2196F3; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .day-item { margin: 10px 0; padding: 10px; background: white; border-left: 3px solid #2196F3; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📊 Weekly Weather Report</h2>
                    </div>
                    <div class='content'>
                        <div class='day-item'>
                            <strong>Saturday:</strong> Sunny, 18°C / 8°C
                        </div>
                        <div class='day-item'>
                            <strong>Sunday:</strong> Partly Cloudy, 16°C / 7°C
                        </div>
                        <div class='day-item'>
                            <strong>Monday:</strong> Cloudy, 14°C / 6°C
                        </div>
                        <div class='day-item'>
                            <strong>Tuesday:</strong> Rainy, 12°C / 5°C
                        </div>
                        <div class='day-item'>
                            <strong>Wednesday:</strong> Sunny, 17°C / 9°C
                        </div>
                        <div class='day-item'>
                            <strong>Thursday:</strong> Partly Cloudy, 15°C / 7°C
                        </div>
                        <div class='day-item'>
                            <strong>Friday:</strong> Sunny, 19°C / 10°C
                        </div>
                    </div>
                    <div class='footer'>
                        This email was sent automatically.
                    </div>
                </div>
            </body>
            </html>
            ";
        }
    }

    private function buildWeeklyReportText(string $lang): string
    {
        if ($lang === 'fa') {
            return "
📊 گزارش هفتگی هواشناسی

شنبه: آفتابی، 18°C / 8°C
یکشنبه: نیمه ابری، 16°C / 7°C
دوشنبه: ابری، 14°C / 6°C
سه‌شنبه: بارانی، 12°C / 5°C
چهارشنبه: آفتابی، 17°C / 9°C
پنج‌شنبه: نیمه ابری، 15°C / 7°C
جمعه: آفتابی، 19°C / 10°C
            ";
        } else {
            return "
📊 Weekly Weather Report

Saturday: Sunny, 18°C / 8°C
Sunday: Partly Cloudy, 16°C / 7°C
Monday: Cloudy, 14°C / 6°C
Tuesday: Rainy, 12°C / 5°C
Wednesday: Sunny, 17°C / 9°C
Thursday: Partly Cloudy, 15°C / 7°C
Friday: Sunny, 19°C / 10°C
            ";
        }
    }

    private function buildMonthlyReportHtml(string $lang): string
    {
        if ($lang === 'fa') {
            return "
            <!DOCTYPE html>
            <html dir='rtl' lang='fa'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #FF9800; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .stat-item { margin: 10px 0; padding: 10px; background: white; border-right: 3px solid #FF9800; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📈 گزارش ماهانه هواشناسی</h2>
                    </div>
                    <div class='content'>
                        <div class='stat-item'>
                            <strong>📅 دوره:</strong> " . now()->subMonth()->format('Y-m-d') . " تا " . now()->format('Y-m-d') . "
                        </div>
                        <div class='stat-item'>
                            <strong>🌡️ میانگین دما:</strong> 14.5°C<br>
                            <strong>🔥 حداکثر دما:</strong> 25°C<br>
                            <strong>❄️ حداقل دما:</strong> 3°C
                        </div>
                        <div class='stat-item'>
                            <strong>💧 مجموع بارش:</strong> 45 mm<br>
                            <strong>💨 میانگین سرعت باد:</strong> 10 km/h<br>
                            <strong>☀️ روزهای آفتابی:</strong> 18 روز
                        </div>
                    </div>
                    <div class='footer'>
                        این ایمیل به صورت خودکار ارسال شده است.
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            return "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #FF9800; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .stat-item { margin: 10px 0; padding: 10px; background: white; border-left: 3px solid #FF9800; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📈 Monthly Weather Report</h2>
                    </div>
                    <div class='content'>
                        <div class='stat-item'>
                            <strong>📅 Period:</strong> " . now()->subMonth()->format('Y-m-d') . " to " . now()->format('Y-m-d') . "
                        </div>
                        <div class='stat-item'>
                            <strong>🌡️ Average Temperature:</strong> 14.5°C<br>
                            <strong>🔥 Maximum Temperature:</strong> 25°C<br>
                            <strong>❄️ Minimum Temperature:</strong> 3°C
                        </div>
                        <div class='stat-item'>
                            <strong>💧 Total Precipitation:</strong> 45 mm<br>
                            <strong>💨 Average Wind Speed:</strong> 10 km/h<br>
                            <strong>☀️ Sunny Days:</strong> 18 days
                        </div>
                    </div>
                    <div class='footer'>
                        This email was sent automatically.
                    </div>
                </div>
            </body>
            </html>
            ";
        }
    }

    private function buildMonthlyReportText(string $lang): string
    {
        if ($lang === 'fa') {
            return "
📈 گزارش ماهانه هواشناسی

📅 دوره: " . now()->subMonth()->format('Y-m-d') . " تا " . now()->format('Y-m-d') . "

🌡️ میانگین دما: 14.5°C
🔥 حداکثر دما: 25°C
❄️ حداقل دما: 3°C

💧 مجموع بارش: 45 mm
💨 میانگین سرعت باد: 10 km/h
☀️ روزهای آفتابی: 18 روز
            ";
        } else {
            return "
📈 Monthly Weather Report

📅 Period: " . now()->subMonth()->format('Y-m-d') . " to " . now()->format('Y-m-d') . "

🌡️ Average Temperature: 14.5°C
🔥 Maximum Temperature: 25°C
❄️ Minimum Temperature: 3°C

💧 Total Precipitation: 45 mm
💨 Average Wind Speed: 10 km/h
☀️ Sunny Days: 18 days
            ";
        }
    }

    private function buildAlertEmailHtml(string $alertType, string $lang): string
    {
        $alertTypes = [
            'temperature' => $lang === 'fa' ? 'دما' : 'Temperature',
            'precipitation' => $lang === 'fa' ? 'بارش' : 'Precipitation',
            'wind' => $lang === 'fa' ? 'باد' : 'Wind',
            'snow' => $lang === 'fa' ? 'برف' : 'Snow',
        ];

        $alertIcons = [
            'temperature' => '🌡️',
            'precipitation' => '🌧️',
            'wind' => '💨',
            'snow' => '❄️',
        ];

        $alertMessages = [
            'temperature' => $lang === 'fa' 
                ? 'تغییرات دما بیش از 5 درجه است'
                : 'Temperature change exceeds 5 degrees',
            'precipitation' => $lang === 'fa'
                ? 'بارش بیش از 10 میلی‌متر است'
                : 'Precipitation exceeds 10 mm',
            'wind' => $lang === 'fa'
                ? 'سرعت باد بیش از 20 کیلومتر بر ساعت است'
                : 'Wind speed exceeds 20 km/h',
            'snow' => $lang === 'fa'
                ? 'بارش برف بیش از 5 سانتی‌متر است'
                : 'Snowfall exceeds 5 cm',
        ];

        if ($lang === 'fa') {
            return "
            <!DOCTYPE html>
            <html dir='rtl' lang='fa'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Tahoma, Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #f44336; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .alert-item { margin: 15px 0; padding: 15px; background: white; border-right: 3px solid #f44336; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🚨 هشدار هواشناسی</h2>
                    </div>
                    <div class='content'>
                        <div class='alert-item'>
                            <strong>{$alertIcons[$alertType]} نوع هشدار:</strong> {$alertTypes[$alertType]}
                        </div>
                        <div class='alert-item'>
                            <strong>📍 موقعیت:</strong> تهران، ایران<br>
                            <strong>📅 تاریخ و زمان:</strong> " . now()->format('Y-m-d H:i') . "
                        </div>
                        <div class='alert-item'>
                            <strong>⚠️ پیام:</strong><br>
                            {$alertMessages[$alertType]}
                        </div>
                        <div class='alert-item'>
                            <strong>💡 توصیه:</strong><br>
                            لطفاً شرایط آب و هوایی را بررسی کنید و اقدامات لازم را انجام دهید.
                        </div>
                    </div>
                    <div class='footer'>
                        این ایمیل به صورت خودکار ارسال شده است.
                    </div>
                </div>
            </body>
            </html>
            ";
        } else {
            return "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #f44336; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
                    .alert-item { margin: 15px 0; padding: 15px; background: white; border-left: 3px solid #f44336; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🚨 Weather Alert</h2>
                    </div>
                    <div class='content'>
                        <div class='alert-item'>
                            <strong>{$alertIcons[$alertType]} Alert Type:</strong> {$alertTypes[$alertType]}
                        </div>
                        <div class='alert-item'>
                            <strong>📍 Location:</strong> Tehran, Iran<br>
                            <strong>📅 Date & Time:</strong> " . now()->format('Y-m-d H:i') . "
                        </div>
                        <div class='alert-item'>
                            <strong>⚠️ Message:</strong><br>
                            {$alertMessages[$alertType]}
                        </div>
                        <div class='alert-item'>
                            <strong>💡 Recommendation:</strong><br>
                            Please check weather conditions and take necessary actions.
                        </div>
                    </div>
                    <div class='footer'>
                        This email was sent automatically.
                    </div>
                </div>
            </body>
            </html>
            ";
        }
    }

    private function buildAlertEmailText(string $alertType, string $lang): string
    {
        $alertTypes = [
            'temperature' => $lang === 'fa' ? 'دما' : 'Temperature',
            'precipitation' => $lang === 'fa' ? 'بارش' : 'Precipitation',
            'wind' => $lang === 'fa' ? 'باد' : 'Wind',
            'snow' => $lang === 'fa' ? 'برف' : 'Snow',
        ];

        $alertIcons = [
            'temperature' => '🌡️',
            'precipitation' => '🌧️',
            'wind' => '💨',
            'snow' => '❄️',
        ];

        $alertMessages = [
            'temperature' => $lang === 'fa' 
                ? 'تغییرات دما بیش از 5 درجه است'
                : 'Temperature change exceeds 5 degrees',
            'precipitation' => $lang === 'fa'
                ? 'بارش بیش از 10 میلی‌متر است'
                : 'Precipitation exceeds 10 mm',
            'wind' => $lang === 'fa'
                ? 'سرعت باد بیش از 20 کیلومتر بر ساعت است'
                : 'Wind speed exceeds 20 km/h',
            'snow' => $lang === 'fa'
                ? 'بارش برف بیش از 5 سانتی‌متر است'
                : 'Snowfall exceeds 5 cm',
        ];

        if ($lang === 'fa') {
            return "
🚨 هشدار هواشناسی

{$alertIcons[$alertType]} نوع هشدار: {$alertTypes[$alertType]}

📍 موقعیت: تهران، ایران
📅 تاریخ و زمان: " . now()->format('Y-m-d H:i') . "

⚠️ پیام:
{$alertMessages[$alertType]}

💡 توصیه:
لطفاً شرایط آب و هوایی را بررسی کنید و اقدامات لازم را انجام دهید.
            ";
        } else {
            return "
🚨 Weather Alert

{$alertIcons[$alertType]} Alert Type: {$alertTypes[$alertType]}

📍 Location: Tehran, Iran
📅 Date & Time: " . now()->format('Y-m-d H:i') . "

⚠️ Message:
{$alertMessages[$alertType]}

💡 Recommendation:
Please check weather conditions and take necessary actions.
            ";
        }
    }
}
