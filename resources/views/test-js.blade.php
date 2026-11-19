@extends('layouts.web')

@section('title', 'تست JavaScript')

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
        max-width: 800px;
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
    .chart-container {
        margin: 30px 0;
        padding: 20px;
        background-color: #f8f9fa;
        border-radius: 8px;
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="header">
        <h1>🧪 تست JavaScript و Chart.js</h1>
        <p>این صفحه برای تست لود شدن Chart.js و نمایش نمودار استفاده می‌شود.</p>
    </div>

    <div class="chart-container">
        <h3 style="text-align: center; margin-bottom: 15px;">📊 نمودار تست</h3>
        <canvas id="testChart" style="max-height: 300px;"></canvas>
    </div>

    <div style="text-align: center; margin-top: 30px; padding: 20px; background-color: #d4edda; border-radius: 8px;">
        <p style="color: #155724; font-weight: bold;">✅ اگر نمودار بالا نمایش داده شد، Chart.js به درستی لود شده است!</p>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('testChart');
    if (ctx && typeof Chart !== 'undefined') {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'],
                datasets: [{
                    label: 'رکعات',
                    data: [12, 19, 15, 8, 10, 14, 16],
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
        console.log('✅ Chart.js loaded and chart created successfully!');
    } else {
        console.error('❌ Chart.js not loaded or canvas not found!');
    }
</script>
@endpush
@endsection
