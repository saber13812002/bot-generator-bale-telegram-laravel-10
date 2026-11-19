# =============================================
# 🔍 بررسی وضعیت Webhook ربات‌های قرآنی
# این اسکریپت با استفاده از API تلگرام
# وضعیت webhook هر ربات را چک می‌کند
# =============================================

param(
    [string]$DB_HOST = "127.0.0.1",
    [string]$DB_USER = "root",
    [string]$DB_PASS = "",
    [string]$DB_NAME = "berimbasket_bot_generator_promoter"
)

Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  🔍 بررسی وضعیت Webhook ربات‌های قرآنی" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""

# کشیدن توکن‌های ربات‌ها از دیتابیس
Write-Host "📡 دریافت لیست ربات‌ها از دیتابیس..." -ForegroundColor Yellow

# MySQL query to get bot tokens
$mysqlCommand = @"
SELECT 
    id,
    telegram_bot_name,
    telegram_bot_token,
    telegram_bot_status,
    telegram_webhook_is_set,
    language_code
FROM bots 
WHERE telegram_bot_name IS NOT NULL 
  AND telegram_bot_token IS NOT NULL
  AND telegram_bot_token != ''
  AND bot_mother_id = 1
ORDER BY language_code;
"@

try {
    $bots = mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME -e "$mysqlCommand" --silent 2>$null
    
    if (-not $bots) {
        Write-Host "⚠️  هیچ ربات تلگرامی در دیتابیس یافت نشد!" -ForegroundColor Red
        Write-Host ""
        Write-Host "📋 سعی می‌کنم از اطلاعات config/quran_bots.php استفاده کنم..." -ForegroundColor Yellow
        Write-Host ""
        Write-Host "لیست ربات‌های قرآنی (از فایل کانفیگ):" -ForegroundColor Green
        
        $botsList = @(
            @{Name="Quran_Hifzbot"; Lang="ar-IQ"; Desc="عربی"},
            @{Name="Quran_urdubot"; Lang="ur"; Desc="اردو"},
            @{Name="Gulanjing_yuedu_bot"; Lang="zh-CN"; Desc="چینی"},
            @{Name="Coran_spanish_bot"; Lang="es"; Desc="اسپانیایی"},
            @{Name="KoranTextBot"; Lang="de-DE"; Desc="آلمانی"},
            @{Name="Coran_Texte_bot"; Lang="fr"; Desc="فرانسوی"},
            @{Name="Tilawat_Quran_Bot"; Lang="en"; Desc="انگلیسی"},
            @{Name="hefzaquran_word_daily_bot"; Lang="fa"; Desc="فارسی"},
            @{Name="Koran_chteniye_bot"; Lang="ru"; Desc="روسی"},
            @{Name="Kurani_Kerim_bot"; Lang="tr"; Desc="ترکی"},
            @{Name="Quran_in_Hebrew_translation_BOT"; Lang="he"; Desc="عبری"}
        )
        
        # Telegram API rate limit: ~30 requests per second
        foreach ($bot in $botsList) {
            $botName = $bot.Name
            $lang = $bot.Lang
            $desc = $bot.Desc
            
            Write-Host "  ─────────────────────────────────────" -ForegroundColor DarkGray
            Write-Host "  🌐 @$botName ($desc - $lang)" -ForegroundColor White
            
            # Get bot info from Telegram API
            $telegramApiUrl = "https://api.telegram.org/bot{TOKEN_NEEDED}/getMe"
            Write-Host "  ⚠️  برای این ربات توکن در دیتابیس موجود نیست" -ForegroundColor Red
            Write-Host "  ⚠️  برای بررسی webhook باید توکن را در .env قرار دهید" -ForegroundColor Yellow
            Write-Host ""
        }
    } else {
        foreach ($bot in $bots) {
            $parts = $bot -split "\t"
            $id = $parts[0]
            $name = $parts[1]
            $token = $parts[2]
            $status = $parts[3]
            $webhookSet = $parts[4]
            $lang = $parts[5]
            
            if (-not $token) { continue }
            
            Write-Host "  ─────────────────────────────────────" -ForegroundColor DarkGray
            Write-Host "  🌐 @$name ($lang)" -ForegroundColor White
            
            try {
                # Check via Telegram API
                $response = Invoke-RestMethod -Uri "https://api.telegram.org/bot$token/getWebhookInfo" -Method Get -TimeoutSec 10
                
                if ($response.ok) {
                    $webhookInfo = $response.result
                    $hookUrl = $webhookInfo.url
                    $hasCustomCertificate = $webhookInfo.has_custom_certificate
                    $pendingCount = $webhookInfo.pending_update_count
                    $lastError = $webhookInfo.last_error_message
                    $lastErrorDate = $webhookInfo.last_error_date
                    $maxConnections = $webhookInfo.max_connections
                    $ipAddress = $webhookInfo.ip_address
                    
                    if ($hookUrl -and $hookUrl -ne "") {
                        Write-Host "  ✅ Webhook SET" -ForegroundColor Green
                        Write-Host "     📍 URL    : $hookUrl" -ForegroundColor Gray
                        Write-Host "     📊 Pending: $pendingCount" -ForegroundColor $(if ($pendingCount -gt 0) { "Yellow" } else { "Green" })
                        Write-Host "     🔗 Max Conn: $maxConnections" -ForegroundColor Gray
                        if ($ipAddress) { Write-Host "     🌍 IP     : $ipAddress" -ForegroundColor Gray }
                        
                        if ($lastError) {
                            $errorDateStr = if ($lastErrorDate) { (Get-Date 01.01.1970) + ([System.TimeSpan]::FromSeconds($lastErrorDate)) } else { "نامشخص" }
                            Write-Host "  ❌ آخرین خطا: $lastError (در تاریخ $errorDateStr)" -ForegroundColor Red
                        } else {
                            Write-Host "  ✅ بدون خطا" -ForegroundColor Green
                        }
                    } else {
                        Write-Host "  ❌ Webhook NOT SET!" -ForegroundColor Red
                        Write-Host "     ⚠️  برای تنظیم وب‌هوک از دستور زیر استفاده کنید:" -ForegroundColor Yellow
                        Write-Host "     httpS://api.telegram.org/bot$token/setWebhook?url=https://YOUR_DOMAIN/api/webhook-quran-word?language=$lang&bot_mother_id=1" -ForegroundColor Gray
                    }
                    
                    # Check bot info
                    $meResponse = Invoke-RestMethod -Uri "https://api.telegram.org/bot$token/getMe" -Method Get -TimeoutSec 10
                    if ($meResponse.ok) {
                        $botUser = $meResponse.result
                        Write-Host "     👤 Bot    : @$($botUser.username) ($($botUser.first_name))" -ForegroundColor Gray
                        $userCount = $botUser.active_usernames_count
                        # Check if can get chat member count (not available via API without chat_id)
                    }
                }
            } catch {
                Write-Host "  ❌ خطا در ارتباط با API تلگرام: $_" -ForegroundColor Red
            }
            
            # Rate limiting
            Start-Sleep -Milliseconds 200
        }
    }
} catch {
    Write-Host "⚠️  خطا در اتصال به دیتابیس: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "📋 لطفا اطلاعات دیتابیس را در متغیرهای ابتدای اسکریپت تنظیم کنید." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  📊 خلاصه" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "📌 ربات‌های قرآنی (11 زبان):" -ForegroundColor Yellow
Write-Host "  🌍 @Koran_chteniye_bot - روسی (شما پرسیدید)" -ForegroundColor White
Write-Host "  🇸🇦 @Quran_Hifzbot - عربی" -ForegroundColor White
Write-Host "  🇵🇰 @Quran_urdubot - اردو" -ForegroundColor White
Write-Host "  🇨🇳 @Gulanjing_yuedu_bot - چینی" -ForegroundColor White
Write-Host "  🇪🇸 @Coran_spanish_bot - اسپانیایی" -ForegroundColor White
Write-Host "  🇩🇪 @KoranTextBot - آلمانی" -ForegroundColor White
Write-Host "  🇫🇷 @Coran_Texte_bot - فرانسوی" -ForegroundColor White
Write-Host "  🇬🇧 @Tilawat_Quran_Bot - انگلیسی" -ForegroundColor White
Write-Host "  🇮🇷 @hefzaquran_word_daily_bot - فارسی" -ForegroundColor White
Write-Host "  🇹🇷 @Kurani_Kerim_bot - ترکی" -ForegroundColor White
Write-Host "  🇮🇱 @Quran_in_Hebrew_translation_BOT - عبری" -ForegroundColor White
Write-Host ""
Write-Host "💡 نکته: توکن‌ها در فایل .env یا دیتابیس ذخیره شده‌اند" -ForegroundColor Green
Write-Host "   برای بررسی کامل، اسکریپت باید با دسترسی به دیتابیس اجرا شود" -ForegroundColor Green
