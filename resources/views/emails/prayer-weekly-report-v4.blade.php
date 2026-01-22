<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Tahoma; direction: rtl; background: #e3f2fd; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 25px; border-radius: 8px; }
        .header { background: #2196f3; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; margin: -25px -25px 20px -25px; }
        .stat { padding: 10px; border-bottom: 1px dashed #ccc; }
        .footer { text-align: center; margin-top: 20px; font-size: 11px; color: #757575; }
        .report-box { margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; text-align: center; color: white; }
        .report-box a { display: inline-block; padding: 12px 30px; background-color: white; color: #667eea; text-decoration: none; border-radius: 25px; font-weight: bold; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        {{-- باکس لینک گزارش --}}
        @if(!empty($reportUrl))
        <div class="report-box">
            <h3 style="margin: 0 0 10px 0;">📊 گزارش کامل شما آماده است!</h3>
            <p style="margin: 0; line-height: 1.6;">برای مشاهده نمودارهای تعاملی و آمار دقیق، روی دکمه زیر کلیک کنید.</p>
            <a href="{{ $reportUrl }}">🌐 مشاهده گزارش کامل</a>
        </div>
        @endif

        <div class="header">
            <h2>گزارش نماز قضا</h2>
        </div>
        <div class="stat">رکعات این هفته: {{ $reportData['stats_by_type']['total_rakats'] ?? 0 }}</div>
        <div class="stat">تعداد ثبت: {{ count($reportData['records']) }}</div>
        @if(!empty($reportData['progress']))
        <div class="stat">درصد پیشرفت: {{ $reportData['progress']['percentage'] }}%</div>
        @endif
        <p style="text-align: center; margin-top: 20px;">💪 ادامه دهید!</p>
        <div class="footer">
            <a href="{{ url('/email/unsubscribe/' . $unsubscribeToken) }}">لغو اشتراک</a>
        </div>
    </div>
</body>
</html>
