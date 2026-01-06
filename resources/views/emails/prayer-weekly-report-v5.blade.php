<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial; direction: rtl; background: #fafafa; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #4caf50; border-bottom: 3px solid #4caf50; padding-bottom: 10px; }
        table { width: 100%; margin: 20px 0; }
        td { padding: 10px; }
        .label { color: #757575; }
        .value { font-weight: bold; color: #4caf50; text-align: left; }
        .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #9e9e9e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 گزارش هفتگی</h1>
        <table>
            <tr><td class="label">رکعات ثبت شده:</td><td class="value">{{ $reportData['stats_by_type']['total_rakats'] ?? 0 }}</td></tr>
            <tr><td class="label">تعداد ثبت:</td><td class="value">{{ count($reportData['records']) }}</td></tr>
            @if(!empty($reportData['progress']))
            <tr><td class="label">پیشرفت:</td><td class="value">{{ $reportData['progress']['percentage'] }}%</td></tr>
            <tr><td class="label">باقی‌مانده:</td><td class="value">{{ $reportData['progress']['remaining'] }}</td></tr>
            @endif
        </table>
        <p style="text-align: center; color: #4caf50;">🌟 همین‌طور پیش بروید!</p>
        <div class="footer">
            <a href="{{ url('/email/unsubscribe/' . $unsubscribeToken) }}">لغو اشتراک</a>
        </div>
    </div>
</body>
</html>
