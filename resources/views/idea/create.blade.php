@extends('layouts.web')

@section('title', '💡 ارسال ایده')

@push('styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Tahoma, 'Segoe UI', system-ui, sans-serif; background: #f9fafb; color: #1f2937; }
.min-h-screen { min-height: 100vh; }
.max-w-2xl { max-width: 672px; margin: 0 auto; }
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
.text-gray-600 { color: #4b5563; }
.text-white { color: #fff; }
.bg-white { background: #fff; }
.bg-red-600 { background: #dc2626; }
.bg-green-50 { background: #f0fdf4; }
.border { border: 1px solid #d1d5db; }
.border-green-200 { border-color: #bbf7d0; }
.rounded { border-radius: 8px; }
.rounded-lg { border-radius: 12px; }
.shadow { box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.w-full { width: 100%; }
.hover\:bg-red-700:hover { background: #b91c1c; }
label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 4px; }
input, textarea, select { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 12px; font-size: 14px; }
input:focus, textarea:focus, select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
button { cursor: pointer; border: none; padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 500; }
</style>
@endpush

@section('content')
<div class="min-h-screen">
    <header class="bg-white shadow-sm">
        <div class="max-w-2xl mx-auto px-4 py-4">
            <a href="{{ url()->previous() }}" class="text-gray-600 hover:text-red-600 text-sm">← بازگشت</a>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-2">💡 ارسال ایده</h1>
        <p class="text-gray-600 mb-6">هر ایده‌ای برای بهبود ربات‌ها داری، برامون بفرست. همه ایده‌ها رو می‌خونم و بررسی می‌کنم.</p>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded p-4 mb-6 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST" action="{{ route('idea.store') }}">
                @csrf

                @if($endpointId)
                    <input type="hidden" name="endpoint_id" value="{{ $endpointId }}">
                @endif

                <div class="mb-4">
                    <label>عنوان ایده *</label>
                    <input type="text" name="title" required maxlength="255" placeholder="مثلاً: اضافه کردن قابلیت جستجوی صوتی">
                    @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label>توضیحات *</label>
                    <textarea name="description" required rows="5" placeholder="ایده خود را با جزئیات توضیح دهید..."></textarea>
                    @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label>نام شما (اختیاری)</label>
                    <input type="text" name="submitter_name" maxlength="100" placeholder="نام یا نام مستعار">
                </div>

                <div class="mb-4">
                    <label>راه ارتباطی (اختیاری)</label>
                    <input type="text" name="submitter_contact" maxlength="200" placeholder="آیدی تلگرام/بله یا ایمیل">
                </div>

                <button type="submit" class="w-full bg-red-600 text-white hover:bg-red-700">
                    ✅ ارسال ایده
                </button>
            </form>
        </div>
    </main>
</div>
@endsection
