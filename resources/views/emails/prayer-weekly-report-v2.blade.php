<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیشرفت هفتگی</title>
    <style>
        body {
            font-family: Vazir, Tahoma, sans-serif;
            direction: rtl;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 15px;
            padding: 40px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h1 {
            color: #667eea;
            font-size: 26px;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background-color: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #6c757d;
            margin-top: 5px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            font-size: 12px;
        }
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

        <div class="header">
            <h1>📈 پیشرفت هفتگی شما</h1>
        </div>

        @if(!empty($reportData['progress']))
        <div class="progress-bar">
            <div class="progress-fill" style="width: {{ $reportData['progress']['percentage'] }}%"></div>
        </div>
        <p style="text-align: center; color: #667eea;">{{ $reportData['progress']['percentage'] }}% تکمیل شده</p>
        @endif

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">{{ $reportData['stats_by_type']['total_rakats'] ?? 0 }}</div>
                <div class="stat-label">رکعت این هفته</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">{{ count($reportData['records']) }}</div>
                <div class="stat-label">ثبت انجام شده</div>
            </div>
        </div>

        <p style="text-align: center; margin-top: 30px;">
            ✨ هر رکعتی که می‌خوانید، قدمی به جلو است!
        </p>

        <div class="footer">
            <p>گزارش هفتگی ربات نماز قضا</p>
            <a href="{{ url('/email/unsubscribe/' . $unsubscribeToken) }}" style="color: #6c757d;">لغو اشتراک</a>
        </div>
    </div>
</body>
</html>
