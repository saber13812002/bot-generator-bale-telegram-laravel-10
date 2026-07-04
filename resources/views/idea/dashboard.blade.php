@extends('layouts.web')

@section('title', '📋 کارتابل ایده‌ها')

@push('styles')
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Tahoma, 'Segoe UI', system-ui, sans-serif; background: #f9fafb; color: #1f2937; }
.max-w-3xl { max-width: 768px; margin: 0 auto; }
.mx-auto { margin-left: auto; margin-right: auto; }
.px-4 { padding-left: 16px; padding-right: 16px; }
.py-4 { padding-top: 16px; padding-bottom: 16px; }
.py-8 { padding-top: 32px; padding-bottom: 32px; }
.p-6 { padding: 24px; }
.p-4 { padding: 16px; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mb-6 { margin-bottom: 24px; }
.text-xl { font-size: 20px; font-weight: 700; }
.text-lg { font-size: 18px; font-weight: 600; }
.text-sm { font-size: 14px; }
.text-xs { font-size: 12px; }
.text-gray-400 { color: #9ca3af; }
.text-gray-500 { color: #6b7280; }
.text-gray-600 { color: #4b5563; }
.text-gray-700 { color: #374151; }
.text-white { color: #fff; }
.bg-white { background: #fff; }
.bg-gray-50 { background: #f9fafb; }
.bg-red-50 { background: #fef2f2; }
.bg-green-50 { background: #f0fdf4; }
.bg-yellow-50 { background: #fefce8; }
.border { border: 1px solid #e5e7eb; }
.border-r-4 { border-right-width: 4px; }
.border-red-500 { border-color: #ef4444; }
.border-green-500 { border-color: #22c55e; }
.border-yellow-500 { border-color: #eab308; }
.border-blue-500 { border-color: #3b82f6; }
.rounded { border-radius: 8px; }
.rounded-lg { border-radius: 12px; }
.shadow { box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.p-3 { padding: 12px; }
.inline-block { display: inline-block; }
.hover\:shadow:hover { box-shadow: 0 4px 6px rgba(0,0,0,.1); }
.transition { transition: all .2s; }
a { text-decoration: none; color: inherit; }
.space-y-3 > * + * { margin-top: 12px; }
</style>
@endpush

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-xl">📋 کارتابل ایده‌های من</h1>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('idea.create') }}" class="text-blue-600 hover:underline">+ ایده جدید</a>
            <form method="POST" action="{{ route('idea.logout') }}">
                @csrf
                <button type="submit" class="text-red-600 hover:underline">خروج</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded p-4 mb-4 text-sm">{{ session('success') }}</div>
    @endif

    @if($ideas->isEmpty())
        <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
            <p>هنوز ایده‌ای ثبت نکرده‌اید.</p>
            <a href="{{ route('idea.create') }}" class="text-blue-600 hover:underline mt-2 inline-block">ثبت اولین ایده</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($ideas as $idea)
                <a href="{{ route('idea.show', $idea->tracking_code) }}" class="block bg-white rounded-lg shadow p-4 hover:shadow transition border-r-4
                    @if($idea->status === 'done' || $idea->status === 'approved') border-green-500
                    @elseif($idea->status === 'rejected') border-red-500
                    @elseif($idea->status === 'reviewing') border-blue-500
                    @else border-yellow-500 @endif">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-semibold text-gray-700">{{ $idea->title }}</h3>
                            <p class="text-sm text-gray-500 mt-1">کد: {{ $idea->tracking_code }}</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded bg-gray-50 text-gray-600">
                            {{ $idea->statusLabel() }}
                        </span>
                    </div>
                    <div class="flex gap-3 mt-2 text-xs text-gray-400">
                        <span>{{ $idea->created_at->diffForHumans() }}</span>
                        @if($idea->messages_count ?? false)
                            <span>{{ $idea->messages_count }} پیام</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
