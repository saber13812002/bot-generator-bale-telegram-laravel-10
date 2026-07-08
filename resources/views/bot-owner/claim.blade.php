@extends('layouts.web')

@section('title', trans('bot-owner.claim_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.claim_title') }}</h1>
            <div class="flex gap-4 items-center">
                <a href="{{ route('bot-owner.dashboard') }}" class="text-sm text-gray-600 hover:underline">
                    ← {{ trans('bot-owner.back_to_dashboard') }}
                </a>
                <span class="text-sm text-gray-500">{{ $owner->phone }}</span>
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

        <!-- Info Box -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-blue-900 mb-2">{{ trans('bot-owner.claim_info_title') }}</h2>
            <p class="text-sm text-blue-800">{{ trans('bot-owner.claim_info_text') }}</p>
            <ol class="list-decimal list-inside text-sm text-blue-800 mt-3 space-y-1">
                <li>{{ trans('bot-owner.claim_step_1') }}</li>
                <li>{{ trans('bot-owner.claim_step_2') }}</li>
                <li>{{ trans('bot-owner.claim_step_3') }}</li>
                <li>{{ trans('bot-owner.claim_step_4') }}</li>
            </ol>
        </div>

        <!-- Pending Claims -->
        @if($pendingClaims->isNotEmpty())
            <div class="bg-white rounded shadow overflow-hidden mb-6">
                <div class="px-4 py-3 bg-yellow-50 border-b border-yellow-100">
                    <h3 class="font-medium text-yellow-800">{{ trans('bot-owner.claim_pending_title') }}</h3>
                </div>
                <div class="divide-y">
                    @foreach($pendingClaims as $claim)
                        <div class="p-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="font-medium">
                                        {{ $claim->bot->bale_bot_name ?? $claim->bot->telegram_bot_name ?? __('Bot #:id', ['id' => $claim->bot->id]) }}
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        {{ trans('bot-owner.claim_type') }}: 
                                        <span class="px-2 py-0.5 rounded text-xs {{ $claim->claim_type === 'owner' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $claim->claim_type === 'owner' ? trans('bot-owner.claim_as_owner') : trans('bot-owner.claim_as_admin') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="text-lg font-mono font-bold tracking-wider text-gray-900 bg-gray-100 px-3 py-1 rounded">
                                        {{ $claim->verification_code }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        {{ trans('bot-owner.claim_expires') }}: {{ $claim->expires_at->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3 text-sm text-gray-600 bg-gray-50 rounded p-3">
                                {{ trans('bot-owner.claim_send_instruction') }}
                                <div class="mt-2">
                                    @php
                                        $botUsername = $claim->bot->bale_bot_name ?? $claim->bot->telegram_bot_name;
                                        $link = $claim->bot->type === 'bale' 
                                            ? 'https://ble.ir/' . $botUsername 
                                            : 'https://t.me/' . $botUsername;
                                    @endphp
                                    <a href="{{ $link }}" target="_blank" 
                                       class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition">
                                        {{ trans('bot-owner.claim_open_bot') }} →
                                    </a>
                                    <button onclick="navigator.clipboard.writeText('{{ $claim->verification_code }}')"
                                            class="mr-2 px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition">
                                        📋 {{ trans('bot-owner.claim_copy_code') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Claimable Bots -->
        <div class="bg-white rounded shadow overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b">
                <h3 class="font-medium">{{ trans('bot-owner.claim_available_title') }}</h3>
            </div>

            @if($claimableBots->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-gray-500">{{ trans('bot-owner.claim_no_bots') }}</p>
                    <p class="text-sm text-gray-400 mt-2">{{ trans('bot-owner.claim_no_bots_hint') }}</p>
                </div>
            @else
                <div class="divide-y">
                    @foreach($claimableBots as $bot)
                        <div class="p-4 flex justify-between items-center">
                            <div>
                                <div class="font-medium">
                                    {{ $bot->bale_bot_name ?? $bot->telegram_bot_name ?? __('Bot #:id', ['id' => $bot->id]) }}
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    <span class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $bot->endpoint_id }}</span>
                                    <span class="mx-1">—</span>
                                    <span>{{ $bot->type ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('bot-owner.claim.generate') }}">
                                    @csrf
                                    <input type="hidden" name="bot_id" value="{{ $bot->id }}">
                                    <input type="hidden" name="claim_type" value="owner">
                                    <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition">
                                        {{ trans('bot-owner.claim_as_owner') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('bot-owner.claim.generate') }}">
                                    @csrf
                                    <input type="hidden" name="bot_id" value="{{ $bot->id }}">
                                    <input type="hidden" name="claim_type" value="admin">
                                    <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition">
                                        {{ trans('bot-owner.claim_as_admin') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>
</div>
@endsection
