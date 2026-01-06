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
