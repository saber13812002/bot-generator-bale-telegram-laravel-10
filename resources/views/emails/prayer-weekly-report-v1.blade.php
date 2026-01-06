<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش هفتگی نماز قضا</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin: 0;
        }
        .stats {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            color: #6c757d;
        }
        .stat-value {
            color: #28a745;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 12px;
        }
        .unsubscribe {
            margin-top: 10px;
        }
        .unsubscribe a {
            color: #6c757d;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🕌 گزارش هفتگی نماز قضا</h1>
            <p>{{ $reportData['period']['from'] }} تا {{ $reportData['period']['to'] }}</p>
        </div>

        <div class="stats">
            <div class="stat-item">
                <span class="stat-label">تعداد رکعات این هفته:</span>
                <span class="stat-value">{{ $reportData['stats_by_type']['total_rakats'] ?? 0 }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">تعداد ثبت‌ها:</span>
                <span class="stat-value">{{ count($reportData['records']) }}</span>
            </div>
            
            @if(!empty($reportData['progress']))
            <div class="stat-item">
                <span class="stat-label">پیشرفت کلی:</span>
                <span class="stat-value">{{ $reportData['progress']['percentage'] }}%</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">باقی‌مانده:</span>
                <span class="stat-value">{{ $reportData['progress']['remaining'] }} رکعت</span>
            </div>
            @endif
        </div>

        <p style="text-align: center; color: #28a745; font-size: 16px;">
            💪 عالی پیش می‌روید! ادامه دهید.
        </p>

        <div class="footer">
            <p>این ایمیل به صورت خودکار از سیستم ربات نماز قضا ارسال شده است.</p>
            <div class="unsubscribe">
                <a href="{{ url('/email/unsubscribe/' . $unsubscribeToken) }}">لغو اشتراک</a>
            </div>
        </div>
    </div>
</body>
</html>
