<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش هفتگی نماز قضا</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        .chart-container {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .comparison {
            margin: 20px 0;
            padding: 15px;
            background-color: #e7f3ff;
            border-radius: 8px;
            border-right: 4px solid #2196F3;
        }
        .motivational {
            margin: 20px 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            color: white;
            line-height: 1.8;
        }
        .motivational p {
            margin: 10px 0;
        }
        .top-users {
            margin: 20px 0;
            padding: 15px;
            background-color: #fff3cd;
            border-radius: 8px;
            border-right: 4px solid #ffc107;
        }
        .peak-info {
            margin: 20px 0;
            padding: 15px;
            background-color: #d4edda;
            border-radius: 8px;
            border-right: 4px solid #28a745;
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

        {{-- نمودار 7 روز گذشته --}}
        @if(!empty($reportData['daily_stats']) && count($reportData['daily_stats']) > 0)
        <div class="chart-container">
            <h3 style="text-align: center; margin-bottom: 15px;">📊 فعالیت 7 روز گذشته</h3>
            <canvas id="dailyChart" style="max-height: 300px;"></canvas>
        </div>
        @endif

        {{-- مقایسه با هفته‌های گذشته --}}
        @if(!empty($reportData['weekly_comparison']))
        <div class="comparison">
            <h3 style="margin-top: 0;">📈 مقایسه با هفته‌های گذشته</h3>
            <p><strong>این هفته:</strong> {{ $reportData['weekly_comparison']['current']['rakats'] }} رکعت</p>
            <p><strong>هفته گذشته:</strong> {{ $reportData['weekly_comparison']['last_week']['rakats'] }} رکعت</p>
            @if($reportData['weekly_comparison']['change_vs_last_week'] > 0)
                <p style="color: #28a745; font-weight: bold;">
                    ⬆️ {{ abs($reportData['weekly_comparison']['change_vs_last_week']) }} رکعت بیشتر از هفته گذشته!
                </p>
            @elseif($reportData['weekly_comparison']['change_vs_last_week'] < 0)
                <p style="color: #ffc107;">
                    ⬇️ {{ abs($reportData['weekly_comparison']['change_vs_last_week']) }} رکعت کمتر از هفته گذشته
                </p>
            @else
                <p>➡️ همانند هفته گذشته</p>
            @endif
        </div>
        @endif

        {{-- اطلاعات پیک فعالیت --}}
        @if(!empty($reportData['peak_activity']) && !empty($reportData['completion_time']))
        <div class="peak-info">
            <h3 style="margin-top: 0;">🔥 بهترین عملکرد شما</h3>
            <p>در <strong>{{ $reportData['peak_activity']['month_label'] }}</strong> شما <strong>{{ $reportData['peak_activity']['rakats'] }} رکعت</strong> ثبت کردید!</p>
            @if($reportData['completion_time']['months'] > 0)
            <p style="font-weight: bold; color: #155724;">
                ⏱️ اگر با همان سرعت ادامه دهید، تقریباً <strong>{{ number_format($reportData['completion_time']['months'], 1) }} ماه</strong> دیگر کار تمام می‌شود!
            </p>
            @endif
        </div>
        @endif

        {{-- آمار Top 10 --}}
        @if(!empty($reportData['top_10_users']) && count($reportData['top_10_users']) > 0)
        <div class="top-users">
            <h3 style="margin-top: 0;">🏆 رتبه‌بندی کاربران این هفته</h3>
            <p>کاربران برتر این هفته تا <strong>{{ $reportData['top_10_users'][0]['rakats'] ?? 0 }} رکعت</strong> ثبت کرده‌اند.</p>
            <p>شما: <strong>{{ $reportData['total_rakats'] ?? 0 }} رکعت</strong></p>
            @if(($reportData['top_10_users'][0]['rakats'] ?? 0) > ($reportData['total_rakats'] ?? 0))
                <p style="color: #856404;">
                    💪 فقط {{ ($reportData['top_10_users'][0]['rakats'] ?? 0) - ($reportData['total_rakats'] ?? 0) }} رکعت دیگر تا رسیدن به رتبه اول!
                </p>
            @else
                <p style="color: #155724; font-weight: bold;">🌟 شما در بین برترین‌ها هستید!</p>
            @endif
        </div>
        @endif

        {{-- پیام انگیزشی --}}
        @if(!empty($reportData['motivational_message']))
        <div class="motivational">
            {!! nl2br(e($reportData['motivational_message'])) !!}
        </div>
        @else
        <p style="text-align: center; color: #28a745; font-size: 16px;">
            💪 عالی پیش می‌روید! ادامه دهید.
        </p>
        @endif

        <div class="footer">
            <p>این ایمیل به صورت خودکار از سیستم ربات نماز قضا ارسال شده است.</p>
            <div class="unsubscribe">
                <a href="{{ url('/email/unsubscribe/' . $unsubscribeToken) }}">لغو اشتراک</a>
            </div>
        </div>
    </div>

    @if(!empty($reportData['daily_stats']) && count($reportData['daily_stats']) > 0)
    <script>
        const ctx = document.getElementById('dailyChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode(array_column($reportData['daily_stats'], 'day_name')) !!},
                    datasets: [{
                        label: 'رکعات',
                        data: {!! json_encode(array_column($reportData['daily_stats'], 'rakats')) !!},
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    </script>
    @endif
</body>
</html>
