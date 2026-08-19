<?php

namespace App\Services;

use App\Helpers\AdminHelper;
use App\Helpers\EmailAdminHelper;
use App\Models\ProPurchaseRequest;
use App\Interfaces\Services\EmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProPurchaseNotificationService
{
    private EmailService $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * ارسال اعلان به ادمین‌ها (ربات + ایمیل)
     */
    public function notifyAdmins(ProPurchaseRequest $request): void
    {
        // ارسال پیام به ربات‌ها (بله و تلگرام)
        $this->sendBotNotifications($request);
        
        // ارسال ایمیل به ادمین‌ها
        $this->sendEmailNotifications($request);
    }

    /**
     * ارسال پیام به ربات‌ها
     */
    private function sendBotNotifications(ProPurchaseRequest $request): void
    {
        $botUser = $request->botUser;
        $bot = $request->bot;
        
        $message = "💳 درخواست خرید Pro\n\n";
        $message .= "👤 User ID: {$request->user_identifier}\n";
        $message .= "💬 Chat ID: {$botUser->chat_id}\n";
        $message .= "🤖 Bot: " . ($bot->name ?? "Bot #{$request->bot_id}") . "\n";
        $message .= "🆔 Request ID: {$request->id}\n";
        $planLine = $this->planLine($request);
        if ($planLine !== '') {
            $message .= $planLine."\n";
        }
        $message .= "📅 Created: " . $request->created_at->format('Y-m-d H:i') . "\n\n";
        $message .= "برای تایید از دستور زیر استفاده کنید:\n";
        $message .= "/pro_confirm {$request->id}\n\n";
        $message .= "یا از لینک زیر در Nova:\n";
        $message .= $this->getNovaUrl($request);

        // ارسال به بله
        try {
            EmailAdminHelper::sendToAllAdmins($message, 'bale');
        } catch (\Exception $e) {
            Log::error('❌ [ProPurchaseNotification] Error sending to Bale', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
        }

        // ارسال به تلگرام
        try {
            EmailAdminHelper::sendToAllAdmins($message, 'telegram');
        } catch (\Exception $e) {
            Log::error('❌ [ProPurchaseNotification] Error sending to Telegram', [
                'error' => $e->getMessage(),
                'request_id' => $request->id
            ]);
        }
    }

    /**
     * ارسال ایمیل به ادمین‌ها
     */
    private function sendEmailNotifications(ProPurchaseRequest $request): void
    {
        $adminEmails = $this->getAdminEmails();
        
        if (empty($adminEmails)) {
            Log::warning('⚠️ [ProPurchaseNotification] No admin emails found');
            return;
        }

        $novaUrl = $this->getNovaUrl($request);
        $botUser = $request->botUser;
        $bot = $request->bot;

        $subject = "💳 درخواست خرید Pro - Request #{$request->id}";

        $htmlBody = $this->buildEmailHtml($request, $novaUrl, $botUser, $bot);
        $textBody = $this->buildEmailText($request, $novaUrl, $botUser, $bot);

        foreach ($adminEmails as $email) {
            try {
                $emailData = new \App\Services\Email\EmailData(
                    $email,
                    $subject,
                    $htmlBody,
                    $textBody,
                    'Pro Purchase Request'
                );
                
                $this->emailService->sendEmail($emailData);

                Log::info('✅ [ProPurchaseNotification] Email sent to admin', [
                    'email' => $email,
                    'request_id' => $request->id
                ]);
            } catch (\Exception $e) {
                Log::error('❌ [ProPurchaseNotification] Error sending email', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'request_id' => $request->id
                ]);
            }
        }
    }

    /**
     * ساخت HTML ایمیل
     */
    private function buildEmailHtml(ProPurchaseRequest $request, string $novaUrl, $botUser, $bot): string
    {
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
                .info-row { margin: 10px 0; padding: 10px; background: white; border-right: 3px solid #4CAF50; }
                .button { display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>💳 درخواست خرید Pro</h2>
                </div>
                <div class='content'>
                    <div class='info-row'>
                        <strong>👤 User ID:</strong> {$request->user_identifier}
                    </div>
                    <div class='info-row'>
                        <strong>💬 Chat ID:</strong> {$botUser->chat_id}
                    </div>
                    <div class='info-row'>
                        <strong>🤖 Bot:</strong> " . ($bot->name ?? "Bot #{$request->bot_id}") . "
                    </div>
                    <div class='info-row'>
                        <strong>🆔 Request ID:</strong> {$request->id}
                    </div>
                    <div class='info-row'>
                        <strong>📅 Created:</strong> " . $request->created_at->format('Y-m-d H:i') . "
                    </div>
                    
                    <p style='margin-top: 20px;'>
                        <a href='{$novaUrl}' class='button' style='background: #4CAF50;'>🔗 باز کردن در Nova</a>
                    </p>
                    
                    <p style='margin-top: 20px;'>
                        <a href='" . url("/admin/pro-purchase/{$request->id}/approve") . "' class='button' style='background: #2196F3; margin-right: 10px;'>✅ تایید سریع</a>
                        <a href='" . url("/admin/pro-purchase/{$request->id}/reject") . "' class='button' style='background: #f44336;'>❌ رد</a>
                    </p>
                    
                    <p style='margin-top: 20px; font-size: 14px; color: #666;'>
                        یا از دستور زیر در ربات استفاده کنید:<br>
                        <code>/pro_confirm {$request->id}</code>
                    </p>
                </div>
                <div class='footer'>
                    این ایمیل به صورت خودکار ارسال شده است.
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * ساخت Text ایمیل
     */
    private function buildEmailText(ProPurchaseRequest $request, string $novaUrl, $botUser, $bot): string
    {
        return "
💳 درخواست خرید Pro

👤 User ID: {$request->user_identifier}
💬 Chat ID: {$botUser->chat_id}
🤖 Bot: " . ($bot->name ?? "Bot #{$request->bot_id}") . "
🆔 Request ID: {$request->id}
📅 Created: " . $request->created_at->format('Y-m-d H:i') . "

برای تایید از یکی از روش‌های زیر استفاده کنید:

1. باز کردن در Nova:
{$novaUrl}

2. تایید سریع:
" . url("/admin/pro-purchase/{$request->id}/approve") . "

3. رد درخواست:
" . url("/admin/pro-purchase/{$request->id}/reject") . "

4. یا از دستور زیر در ربات استفاده کنید:
/pro_confirm {$request->id}
        ";
    }

    /**
     * دریافت URL Nova برای درخواست
     */
    private function getNovaUrl(ProPurchaseRequest $request): string
    {
        $baseUrl = env('APP_URL', 'https://bots.pardisania.ir');
        $novaPath = config('nova.path', '/nova');
        return "{$baseUrl}{$novaPath}/resources/pro-purchase-requests/{$request->id}";
    }

    /**
     * دریافت ایمیل‌های ادمین‌ها
     */
    private function getAdminEmails(): array
    {
        $emails = [];
        
        // از env دریافت می‌کنیم
        $adminEmail = env('ADMIN_EMAIL');
        if ($adminEmail) {
            $emails[] = $adminEmail;
        }

        // می‌توانیم از دیتابیس هم دریافت کنیم
        // $admins = User::where('is_admin', true)->pluck('email')->toArray();
        // $emails = array_merge($emails, $admins);

        return array_unique($emails);
    }

    private function planLine(ProPurchaseRequest $request): string
    {
        $raw = $request->payment_info;
        $info = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        if (!is_array($info) || $info === []) {
            return '';
        }
        $plan = $info['plan'] ?? '';
        $months = $info['months'] ?? '';
        $amount = $info['amount'] ?? '';
        $parts = [];
        if ($plan !== '') {
            $parts[] = "📦 Plan: {$plan}";
        }
        if ($months !== '') {
            $parts[] = "⏱ {$months} month(s)";
        }
        if ($amount !== '') {
            $parts[] = "💰 {$amount}";
        }

        return $parts !== [] ? implode(' · ', $parts) : '';
    }
}
