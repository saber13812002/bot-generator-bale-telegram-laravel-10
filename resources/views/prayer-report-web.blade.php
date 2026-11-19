@extends('layouts.web')

@section('title', 'گزارش هفتگی نماز قضا')

@push('styles')
<style>
    body {
        font-family: Tahoma, Arial, sans-serif;
        direction: rtl;
        background-color: #f5f5f5;
        margin: 0;
        padding: 20px;
    }
    .container {
        max-width: 900px;
        margin: 0 auto;
        background-color: #ffffff;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e9ecef;
    }
    .header h1 {
        color: #2c3e50;
        font-size: 28px;
        margin: 0 0 10px 0;
    }
    .header p {
        color: #6c757d;
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
        padding: 12px 0;
        border-bottom: 1px solid #dee2e6;
    }
    .stat-item:last-child {
        border-bottom: none;
    }
    .stat-label {
        color: #6c757d;
        font-size: 16px;
    }
    .stat-value {
        color: #28a745;
        font-weight: bold;
        font-size: 18px;
    }
    .chart-container {
        margin: 30px 0;
        padding: 20px;
        background-color: #f8f9fa;
        border-radius: 8px;
    }
    .comparison {
        margin: 20px 0;
        padding: 20px;
        background-color: #e7f3ff;
        border-radius: 8px;
        border-right: 4px solid #2196F3;
    }
    .comparison h3 {
        margin-top: 0;
        color: #1976D2;
    }
    .motivational {
        margin: 20px 0;
        padding: 25px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        color: white;
        line-height: 1.8;
        font-size: 16px;
    }
    .motivational p {
        margin: 10px 0;
    }
    .top-users {
        margin: 20px 0;
        padding: 20px;
        background-color: #fff3cd;
        border-radius: 8px;
        border-right: 4px solid #ffc107;
    }
    .top-users h3 {
        margin-top: 0;
        color: #856404;
    }
    .peak-info {
        margin: 20px 0;
        padding: 20px;
        background-color: #d4edda;
        border-radius: 8px;
        border-right: 4px solid #28a745;
    }
    .peak-info h3 {
        margin-top: 0;
        color: #155724;
    }
    .footer {
        text-align: center;
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #dee2e6;
        color: #6c757d;
        font-size: 12px;
    }
    .section-title {
        font-size: 20px;
        color: #2c3e50;
        margin: 30px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9ecef;
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="header">
        <h1>🕌 گزارش هفتگی نماز قضا</h1>
        <div style="margin-top: 15px; padding: 15px; background-color: #f8f9fa; border-radius: 8px;">
            <p style="margin: 5px 0; font-size: 16px;">
                <strong>میلادی:</strong> 
                {{ $reportData['dates']['gregorian']['start'] ?? $reportData['period']['from'] ?? now()->subWeek()->toDateString() }} 
                تا 
                {{ $reportData['dates']['gregorian']['end'] ?? $reportData['period']['to'] ?? now()->toDateString() }}
            </p>
            <p style="margin: 5px 0; font-size: 16px;">
                <strong>شمسی:</strong> 
                {{ $reportData['dates']['shamsi']['start'] ?? '' }} 
                تا 
                {{ $reportData['dates']['shamsi']['end'] ?? '' }}
            </p>
            <p style="margin: 5px 0; font-size: 16px;">
                <strong>قمری:</strong> 
                {{ $reportData['dates']['hijri']['start'] ?? '' }} 
                تا 
                {{ $reportData['dates']['hijri']['end'] ?? '' }}
            </p>
        </div>
    </div>

    <div class="stats">
        <div class="stat-item">
            <span class="stat-label">تعداد رکعات این هفته:</span>
            <span class="stat-value">{{ $reportData['stats_by_type']['total_rakats'] ?? 0 }}</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">تعداد ثبت‌ها:</span>
            <span class="stat-value">{{ $reportData['total_prayers'] ?? 0 }}</span>
        </div>
        
        @if(!empty($reportData['progress']))
        <div class="stat-item">
            <span class="stat-label">پیشرفت کلی:</span>
            <span class="stat-value">{{ $reportData['progress']['percentage'] ?? 0 }}%</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">باقی‌مانده:</span>
            <span class="stat-value">{{ $reportData['progress']['remaining'] ?? 0 }} رکعت</span>
        </div>
        @endif
    </div>

    {{-- نمودار 7 روز گذشته --}}
    @if(!empty($reportData['daily_stats']) && count($reportData['daily_stats']) > 0)
    <h2 class="section-title">📊 فعالیت 7 روز گذشته</h2>
    <div class="chart-container">
        <canvas id="dailyChart" style="max-height: 400px;"></canvas>
    </div>
    @endif

    {{-- مقایسه با هفته‌های گذشته --}}
    @if(!empty($reportData['weekly_comparison']))
    <h2 class="section-title">📈 مقایسه با هفته‌های گذشته</h2>
    <div class="comparison">
        <h3>مقایسه عملکرد</h3>
        <p><strong>این هفته:</strong> {{ $reportData['weekly_comparison']['current']['rakats'] }} رکعت ({{ $reportData['weekly_comparison']['current']['count'] }} ثبت)</p>
        <p><strong>هفته گذشته:</strong> {{ $reportData['weekly_comparison']['last_week']['rakats'] }} رکعت ({{ $reportData['weekly_comparison']['last_week']['count'] }} ثبت)</p>
        @if($reportData['weekly_comparison']['change_vs_last_week'] > 0)
            <p style="color: #28a745; font-weight: bold; font-size: 18px; margin-top: 15px;">
                ⬆️ {{ abs($reportData['weekly_comparison']['change_vs_last_week']) }} رکعت بیشتر از هفته گذشته! 🎉
            </p>
        @elseif($reportData['weekly_comparison']['change_vs_last_week'] < 0)
            <p style="color: #ffc107; font-weight: bold; font-size: 18px; margin-top: 15px;">
                ⬇️ {{ abs($reportData['weekly_comparison']['change_vs_last_week']) }} رکعت کمتر از هفته گذشته
            </p>
        @else
            <p style="color: #6c757d; font-weight: bold; font-size: 18px; margin-top: 15px;">
                ➡️ همانند هفته گذشته
            </p>
        @endif
    </div>
    @endif

    {{-- اطلاعات پیک فعالیت --}}
    @if(!empty($reportData['peak_activity']) && !empty($reportData['completion_time']))
    <h2 class="section-title">🔥 بهترین عملکرد شما</h2>
    <div class="peak-info">
        <h3>رکورد شخصی شما</h3>
        <p>در <strong>{{ $reportData['peak_activity']['month_label'] }}</strong> شما <strong>{{ $reportData['peak_activity']['rakats'] }} رکعت</strong> ثبت کردید! 🏆</p>
        @if($reportData['completion_time']['months'] > 0)
        <p style="font-weight: bold; color: #155724; font-size: 18px; margin-top: 15px;">
            ⏱️ اگر با همان سرعت ادامه دهید، تقریباً <strong>{{ number_format($reportData['completion_time']['months'], 1) }} ماه</strong> دیگر کار تمام می‌شود!
        </p>
        @endif
    </div>
    @endif

    {{-- آمار Top 10 --}}
    @if(!empty($reportData['top_10_users']) && count($reportData['top_10_users']) > 0)
    <h2 class="section-title">🏆 رتبه‌بندی کاربران این هفته</h2>
    <div class="top-users">
        <h3>مقایسه با دیگران</h3>
        <p>کاربران برتر این هفته تا <strong>{{ $reportData['top_10_users'][0]['rakats'] ?? 0 }} رکعت</strong> ثبت کرده‌اند.</p>
        <p>شما: <strong>{{ $reportData['total_rakats'] ?? 0 }} رکعت</strong></p>
        @if(($reportData['top_10_users'][0]['rakats'] ?? 0) > ($reportData['total_rakats'] ?? 0))
            <p style="color: #856404; font-weight: bold; font-size: 18px; margin-top: 15px;">
                💪 فقط {{ ($reportData['top_10_users'][0]['rakats'] ?? 0) - ($reportData['total_rakats'] ?? 0) }} رکعت دیگر تا رسیدن به رتبه اول!
            </p>
        @else
            <p style="color: #155724; font-weight: bold; font-size: 18px; margin-top: 15px;">
                🌟 شما در بین برترین‌ها هستید!
            </p>
        @endif
    </div>
    @endif

    {{-- پیام انگیزشی --}}
    @if(!empty($reportData['motivational_message']))
    <h2 class="section-title">💬 پیام انگیزشی</h2>
    <div class="motivational">
        {!! nl2br(e($reportData['motivational_message'])) !!}
    </div>
    @endif

    <div class="footer">
        <p>این گزارش به صورت خودکار از سیستم ربات نماز قضا تولید شده است.</p>
        <p>برای لغو اشتراک از ایمیل‌های هفتگی، از لینک موجود در ایمیل استفاده کنید.</p>
    </div>
</div>

@if(!empty($reportData['daily_stats']) && count($reportData['daily_stats']) > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('dailyChart');
    if (ctx && typeof Chart !== 'undefined') {
        const labels = {!! json_encode(array_column($reportData['daily_stats'], 'day_name')) !!};
        const data = {!! json_encode(array_column($reportData['daily_stats'], 'rakats')) !!};
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'رکعات',
                    data: data,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    borderRadius: 5
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
                        },
                        title: {
                            display: true,
                            text: 'تعداد رکعات'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'روز هفته'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'رکعات: ' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        });
    }
</script>
@endpush
@endif
@endsection
