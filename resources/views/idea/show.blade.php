@extends('layouts.web')

@section('title', '💡 ' . $idea->title)

@push('styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Tahoma, 'Segoe UI', system-ui, sans-serif; background: #f9fafb; color: #1f2937; }
.max-w-2xl { max-width: 672px; margin: 0 auto; }
.mx-auto { margin-left: auto; margin-right: auto; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }
.p-6 { padding: 24px; }
.p-4 { padding: 16px; }
.p-3 { padding: 12px; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-6 { margin-bottom: 24px; }
.text-2xl { font-size: 24px; font-weight: 700; }
.text-lg { font-size: 18px; }
.text-sm { font-size: 14px; }
.text-xs { font-size: 12px; color: #6b7280; }
.text-gray-400 { color: #9ca3af; }
.text-gray-500 { color: #6b7280; }
.text-gray-600 { color: #4b5563; }
.text-gray-700 { color: #374151; }
.text-white { color: #fff; }
.text-blue-600 { color: #2563eb; }
.bg-white { background: #fff; }
.bg-gray-50 { background: #f9fafb; }
.bg-blue-50 { background: #eff6ff; }
.bg-green-50 { background: #f0fdf4; }
.bg-yellow-50 { background: #fefce8; }
.border { border: 1px solid #e5e7eb; }
.rounded { border-radius: 8px; }
.rounded-lg { border-radius: 12px; }
.shadow { box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.shadow-lg { box-shadow: 0 4px 6px rgba(0,0,0,.1); }
.w-full { width: 100%; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.gap-4 { gap: 16px; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.space-y-3 > * + * { margin-top: 12px; }
.space-y-4 > * + * { margin-top: 16px; }
.hover\:underline:hover { text-decoration: underline; }
textarea { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
textarea:focus { outline: none; border-color: #2563eb; }
button { cursor: pointer; border: none; padding: 8px 20px; border-radius: 8px; font-size: 14px; }
.bg-red-600 { background: #dc2626; color: white; }
.hover\:bg-red-700:hover { background: #b91c1c; }
.inline-block { display: inline-block; }
</style>
@endpush

@section('content')
<div class="max-w-2xl mx-auto px-4 py-8">
    <a href="{{ $owner ? route('idea.dashboard') : route('idea.create') }}" class="text-sm text-blue-600 hover:underline mb-4 inline-block">← بازگشت</a>

    <!-- Header -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-4">
        <div class="flex justify-between items-start mb-4">
            <h1 class="text-2xl font-bold">{{ $idea->title }}</h1>
            <span class="text-sm px-3 py-1 rounded bg-gray-50">{{ $idea->statusLabel() }}</span>
        </div>

        <div class="flex gap-4 text-xs text-gray-500 mb-4">
            <span>📋 کد: {{ $idea->tracking_code }}</span>
            <span>📅 {{ $idea->created_at->format('Y/m/d') }}</span>
            @if($idea->endpoint_id)
                <span>🤖 {{ $idea->endpoint_id }}</span>
            @endif
        </div>

        <div class="text-gray-700 leading-relaxed whitespace-pre-line">
            {{ $idea->description }}
        </div>

        @if($idea->email)
            <div class="mt-4 text-xs text-gray-500">
                📧 {{ $idea->email }}
                @if($idea->isEmailVerified())
                    ✅ تأیید شده
                @else
                    <a href="{{ route('idea.verify.email', $idea->tracking_code) }}" class="text-blue-600 hover:underline">تأیید ایمیل</a>
                @endif
            </div>
        @endif
    </div>

    <!-- Messages / Ticket -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-4">
        <h2 class="text-lg font-semibold mb-4">💬 پیام‌ها</h2>

        @if($messages->isEmpty())
            <p class="text-sm text-gray-500 mb-4">هنوز پیامی ثبت نشده است.</p>
        @else
            <div class="space-y-3 mb-4">
                @foreach($messages as $msg)
                    <div class="p-3 rounded @if($msg->sender_type === 'admin') bg-blue-50 border border-blue-200 @else bg-gray-50 border @endif">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-semibold">
                                @if($msg->sender_type === 'admin') 👨‍💼 ادمین
                                @elseif($msg->sender_type === 'system') 🤖 سیستم
                                @else 🙋 {{ $msg->sender_name ?? 'کاربر' }}
                                @endif
                            </span>
                            <span class="text-xs text-gray-400">{{ $msg->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $msg->message }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Reply Form -->
        @if($idea->status !== 'done')
        <form method="POST" action="{{ route('idea.message', $idea->tracking_code) }}">
            @csrf
            <div class="mb-2">
                <textarea name="message" rows="3" required placeholder="پیام خود را بنویسید..."></textarea>
            </div>
            <button type="submit" class="bg-red-600 text-white hover:bg-red-700">ارسال پیام</button>
        </form>
        @endif
    </div>

    <!-- Admin Note (if any) -->
    @if($idea->admin_note)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-sm font-semibold mb-1">📌 یادداشت ادمین</p>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $idea->admin_note }}</p>
        </div>
    @endif
</div>
@endsection
