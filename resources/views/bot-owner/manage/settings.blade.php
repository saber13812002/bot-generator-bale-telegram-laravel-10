@extends('layouts.web')

@section('title', trans('bot-owner.settings_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.settings_title') }}</h1>
            <div class="flex gap-4 items-center">
                <a href="{{ route('bot-owner.manage', $bot->id) }}" class="text-sm text-gray-600 hover:underline">
                    ← {{ trans('bot-owner.back_to_manage') }}
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

        <!-- General Settings -->
        <div class="bg-white rounded shadow p-6 mb-6">
            <h2 class="text-lg font-semibold mb-4">{{ trans('bot-owner.settings_general') }}</h2>
            
            <div class="space-y-4">
                <!-- Bot Name Display -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ trans('bot-owner.bot_type') }}</label>
                    <p class="text-sm text-gray-900">
                        {{ $bot->webhookEndpoint->name ?? $bot->endpoint_id }}
                    </p>
                </div>

                <!-- Bot Token Display -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ trans('bot-owner.bot_token') }}</label>
                    <p class="text-sm text-gray-900">
                        <code class="bg-gray-100 px-2 py-0.5 rounded">
                            {{ $bot->type === 'bale' 
                                ? substr($bot->bale_bot_token, 0, 30) . '...' 
                                : substr($bot->telegram_bot_token, 0, 30) . '...' }}
                        </code>
                    </p>
                </div>

                <!-- Language -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ trans('bot-owner.bot_language') }}</label>
                    <p class="text-sm text-gray-900">{{ $bot->language_code ?? '—' }}</p>
                </div>
            </div>
        </div>

        <!-- Type-Specific Settings -->
        <div class="bg-white rounded shadow p-6">
            <h2 class="text-lg font-semibold mb-4">
                {{ trans('bot-owner.settings_type_specific', ['type' => $bot->webhookEndpoint->name ?? $bot->endpoint_id]) }}
            </h2>

            @if($hasTypeSpecificView)
                @include($typeView)
            @else
                <div class="text-center py-8">
                    <p class="text-gray-500">{{ trans('bot-owner.settings_fallback_message', ['type' => $bot->webhookEndpoint->name ?? $bot->endpoint_id]) }}</p>
                </div>
            @endif
        </div>
    </main>
</div>
@endsection
