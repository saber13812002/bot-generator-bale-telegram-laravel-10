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
        {{-- باکس لینک گزارش --}}
        @if(!empty($reportUrl))
        <div style="margin: 20px 0; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; text-align: center; color: white;">
            <h3 style="margin: 0 0 10px 0;">📊 گزارش کامل شما آماده است!</h3>
            <p style="margin: 0 0 15px 0; line-height: 1.6;">برای مشاهده نمودارهای تعاملی و آمار دقیق، روی دکمه زیر کلیک کنید.</p>
            <a href="{{ $reportUrl }}" style="display: inline-block; padding: 12px 30px; background-color: white; color: #667eea; text-decoration: none; border-radius: 25px; font-weight: bold;">🌐 مشاهده گزارش کامل</a>
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
