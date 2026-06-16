@extends('layouts.web')

@section('title', trans('bot-owner.dashboard_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.dashboard_title') }}</h1>
            <div class="flex gap-4 items-center">
                <span class="text-sm text-gray-500">{{ $owner->phone }}</span>
                @if($stats['is_pro'])
                    <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Pro</span>
                @endif
                <form method="POST" action="{{ route('bot-owner.logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-red-600 hover:underline">{{ trans('bot-owner.logout') }}</button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white p-4 rounded shadow">
                <div class="text-2xl font-bold">{{ $stats['total_bots'] }}</div>
                <div class="text-sm text-gray-500">{{ trans('bot-owner.my_bots') }}</div>
            </div>
            <div class="bg-white p-4 rounded shadow">
                <div class="text-2xl font-bold">{{ $stats['is_pro'] ? '✓' : '—' }}</div>
                <div class="text-sm text-gray-500">{{ trans('bot-owner.pro_status') }}</div>
                @if($stats['is_pro'])
                    <div class="text-xs text-gray-400 mt-1">
                        @if($stats['pro_unlimited'] ?? false)
                            {{ trans('bot-owner.pro_unlimited') }}
                        @elseif($stats['pro_expires_at'])
                            {{ trans('bot-owner.pro_expires_at', ['date' => $stats['pro_expires_at']->format('Y-m-d')]) }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="bg-white p-4 rounded shadow">
                <a href="{{ route('bot-owner.intro') }}" class="text-red-600 hover:underline text-sm">{{ trans('bot-owner.create_new_bot') }}</a>
            </div>
        </div>

        @if(!$stats['is_pro'])
            <div class="bg-yellow-50 border border-yellow-200 rounded p-4 mb-8">
                <p class="text-sm mb-3">{{ trans('bot-owner.pro_upgrade_hint') }}</p>
                @if($stats['has_pending_pro'])
                    <p class="text-sm text-yellow-700">{{ trans('bot-owner.pro_request_pending') }}</p>
                @else
                    <form method="POST" action="{{ route('bot-owner.pro.request') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 text-sm">
                            {{ trans('bot-owner.request_pro') }}
                        </button>
                    </form>
                @endif
            </div>
        @endif

        <h2 class="text-lg font-semibold mb-4">{{ trans('bot-owner.my_bots') }}</h2>
        @if($bots->isEmpty())
            <p class="text-gray-500">{{ trans('bot-owner.no_bots_yet') }}</p>
        @else
            <div class="space-y-3">
                @foreach($bots as $bot)
                    <div class="bg-white p-4 rounded shadow flex justify-between">
                        <div>
                            <div class="font-medium">{{ $bot->bale_bot_name ?? $bot->telegram_bot_name ?? 'Bot #'.$bot->id }}</div>
                            <div class="text-sm text-gray-500">{{ $bot->endpoint_id }} — {{ $bot->type }}</div>
                        </div>
                        <span class="text-xs text-green-600">{{ $bot->bale_bot_status ?? $bot->telegram_bot_status }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-8 text-sm text-gray-500">
            {{ trans('bot-owner.link_bale_hint') }}
            <a href="{{ config('bot.admin_bots_link', 'https://ble.ir') }}" class="text-red-600 hover:underline" target="_blank">
                {{ trans('bot-owner.admin_bots_bot') }}
            </a>
        </p>
    </main>
</div>
@endsection
