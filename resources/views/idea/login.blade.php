@extends('layouts.web')

@section('title', '🔐 ورود - کارتابل ایده‌ها')

@push('styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Tahoma, 'Segoe UI', system-ui, sans-serif; background: #f9fafb; color: #1f2937; }
.min-h-screen { min-height: 100vh; }
.max-w-md { max-width: 448px; margin: 0 auto; }
.mx-auto { margin-left: auto; margin-right: auto; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }
.p-6 { padding: 24px; }
.p-4 { padding: 16px; }
.mb-4 { margin-bottom: 16px; }
.mb-6 { margin-bottom: 24px; }
.text-2xl { font-size: 24px; font-weight: 700; }
.text-sm { font-size: 14px; }
.text-xs { color: #6b7280; font-size: 12px; }
.text-gray-500 { color: #6b7280; }
.text-gray-600 { color: #4b5563; }
.text-white { color: #fff; }
.text-center { text-align: center; }
.bg-white { background: #fff; }
.bg-red-600 { background: #dc2626; }
.bg-green-50 { background: #f0fdf4; }
.bg-red-50 { background: #fef2f2; }
.border { border: 1px solid #d1d5db; }
.border-green-200 { border-color: #bbf7d0; }
.border-red-200 { border-color: #fecaca; }
.rounded { border-radius: 8px; }
.rounded-lg { border-radius: 12px; }
.shadow { box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.shadow-lg { box-shadow: 0 4px 6px rgba(0,0,0,.1); }
.w-full { width: 100%; }
.hover\:bg-red-700:hover { background: #b91c1c; }
label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px; }
input { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
button { cursor: pointer; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; }
</style>
@endpush

@section('content')
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold mb-2">🔐 کارتابل ایده‌ها</h1>
            <p class="text-gray-500">با شماره موبایل بله وارد شوید</p>
        </div>

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 rounded p-4 mb-4 text-sm">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded p-4 mb-4 text-sm">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-lg shadow-lg p-6">
            <form method="POST" action="{{ route('idea.otp.send') }}" class="mb-4">
                @csrf
                <div class="mb-4">
                    <label>شماره موبایل</label>
                    <input type="tel" name="phone" required placeholder="مثال: 09123456789">
                </div>
                <button type="submit" class="w-full bg-red-600 text-white hover:bg-red-700">ارسال کد تأیید</button>
            </form>

            <form method="POST" action="{{ route('idea.otp.verify') }}">
                @csrf
                <div class="mb-4">
                    <label>کد تأیید</label>
                    <input type="text" name="otp" required maxlength="8" placeholder="کد ۶ رقمی را وارد کنید">
                </div>
                <button type="submit" class="w-full bg-green-600 text-white hover:bg-green-700">✅ ورود</button>
            </form>
        </div>

        <p class="text-xs text-gray-500 text-center mt-4">
            <a href="{{ route('idea.create') }}" class="hover:underline">ثبت ایده جدید بدون ورود</a>
        </p>
    </div>
</div>
@endsection
