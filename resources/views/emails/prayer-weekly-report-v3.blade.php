<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; direction: rtl; background-color: #fff3cd; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; }
        h1 { color: #856404; text-align: center; }
        .stats { background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 20px; color: #6c757d; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        {{-- باکس راهنمایی برای کاربرانی که تخمین نزده‌اند --}}
        @if(empty($hasEstimate) || !$hasEstimate)
        <div style="margin: 20px 0; padding: 25px; background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); border-radius: 10px; text-align: center; color: white;">
            <h3 style="margin: 0 0 15px 0; font-size: 20px;">💡 برای دریافت گزارش کامل‌تر</h3>
            <p style="margin: 0 0 10px 0; line-height: 1.8; font-size: 16px;">
                اگر تخمین نماز قضای خود را از طریق ربات اعلام کنید، می‌توانید گزارش کامل‌تر با نمودارهای تعاملی، مقایسه با هفته‌های گذشته و آمار دقیق‌تر دریافت کنید.
            </p>
            <p style="margin: 0; font-size: 14px; opacity: 0.9;">
                📱 به ربات برگردید و تخمین خود را ثبت کنید
            </p>
        </div>
        @elseif(!empty($reportUrl))
        {{-- باکس لینک قابل کپی برای کاربرانی که تخمین زده‌اند --}}
        <div style="margin: 20px 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; text-align: center; color: white;">
            <h3 style="margin: 0 0 15px 0; font-size: 20px;">📊 گزارش کامل شما آماده است!</h3>
            <p style="margin: 0 0 15px 0; line-height: 1.6; font-size: 16px;">
                برای مشاهده نمودارهای تعاملی و آمار دقیق، روی دکمه زیر کلیک کنید.
            </p>
            <a href="{{ $reportUrl }}" style="display: inline-block; padding: 12px 30px; background-color: white; color: #667eea; text-decoration: none; border-radius: 25px; font-weight: bold; margin-bottom: 20px;">🌐 مشاهده گزارش کامل</a>
            
            {{-- باکس قابل کپی برای لینک --}}
            <div style="margin-top: 20px; padding: 15px; background-color: rgba(255, 255, 255, 0.15); border-radius: 8px;">
                <p style="margin: 0 0 10px 0; font-size: 14px; opacity: 0.9;">
                    اگر نمی‌توانید روی لینک کلیک کنید، این آدرس را کپی کنید:
                </p>
                <div id="copyable-link" style="background-color: white; color: #333; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 13px; word-break: break-all; cursor: text; user-select: all; -webkit-user-select: all; -moz-user-select: all; -ms-user-select: all;" ondblclick="this.select(); document.execCommand('copy'); alert('لینک کپی شد!');">
                    {{ $reportUrl }}
                </div>
                <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.8;">
                    💡 راهنما: روی آدرس بالا دابل کلیک کنید تا انتخاب شود، سپس Ctrl+C (یا Cmd+C در Mac) را بزنید تا کپی شود
                </p>
            </div>
        </div>
        @endif

        <h1>🌟 آمار نماز قضای شما</h1>
        <div class="stats">
            <p><strong>این هفته:</strong> {{ $reportData['stats_by_type']['total_rakats'] ?? 0 }} رکعت</p>
            <p><strong>تعداد ثبت:</strong> {{ count($reportData['records']) }}</p>
            @if(!empty($reportData['progress']))
            <p><strong>پیشرفت:</strong> {{ $reportData['progress']['percentage'] }}%</p>
            @endif
        </div>
        <p style="text-align: center;">🎯 به هدفتان نزدیک‌تر می‌شوید!</p>
        <div class="footer">
            <a href="{{ url('/email/unsubscribe/' . $unsubscribeToken) }}">لغو اشتراک</a>
        </div>
    </div>
</body>
</html>
