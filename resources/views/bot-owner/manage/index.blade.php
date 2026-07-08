@extends('layouts.web')

@section('title', trans('bot-owner.manage_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.manage_bot') }}</h1>
            <div class="flex gap-4 items-center">
                <a href="{{ route('bot-owner.library') }}" class="text-sm text-gray-600 hover:underline">
                    ← {{ trans('bot-owner.back_to_library') }}
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

        <!-- Bot Info Card -->
        <div class="bg-white rounded shadow overflow-hidden mb-6">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">
                            {{ $bot->bale_bot_name ?? $bot->telegram_bot_name ?? __('Bot #:id', ['id' => $bot->id]) }}
                        </h2>
                        <div class="mt-2 space-y-1 text-sm text-gray-600">
                            @if($bot->webhookEndpoint)
                                <p><span class="font-medium">{{ trans('bot-owner.bot_type') }}:</span> {{ $bot->webhookEndpoint->name }} ({{ $bot->endpoint_id }})</p>
                            @else
                                <p><span class="font-medium">{{ trans('bot-owner.bot_type') }}:</span> {{ $bot->endpoint_id }}</p>
                            @endif
                            <p><span class="font-medium">{{ trans('bot-owner.bot_platform') }}:</span> {{ $bot->type ?? '—' }}</p>
                            <p><span class="font-medium">{{ trans('bot-owner.bot_token') }}:</span> 
                                <code class="bg-gray-100 px-2 py-0.5 rounded text-xs">
                                    {{ $bot->type === 'bale' ? substr($bot->bale_bot_token, 0, 20) : substr($bot->telegram_bot_token, 0, 20) }}...
                                </code>
                            </p>
                            <p><span class="font-medium">{{ trans('bot-owner.bot_language') }}:</span> {{ $bot->language_code ?? '—' }}</p>
                            <p><span class="font-medium">{{ trans('bot-owner.bot_status') }}:</span> 
                                <span class="px-2 py-0.5 rounded text-xs
                                    @if(($bot->bale_bot_status ?? $bot->telegram_bot_status ?? '') === 'Active')
                                        bg-green-100 text-green-700
                                    @else
                                        bg-gray-100 text-gray-500
                                    @endif">
                                    {{ $bot->bale_bot_status ?? $bot->telegram_bot_status ?? '—' }}
                                </span>
                            </p>
                            <p><span class="font-medium">{{ trans('bot-owner.bot_created') }}:</span> {{ $bot->created_at ? $bot->created_at->format('Y-m-d H:i') : '—' }}</p>
                            @if($bot->last_activity_at)
                                <p><span class="font-medium">{{ trans('bot-owner.bot_last_activity') }}:</span> {{ $bot->last_activity_at instanceof \Carbon\Carbon ? $bot->last_activity_at->diffForHumans() : $bot->last_activity_at }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="border-t border-gray-100 bg-gray-50 px-6 py-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900">{{ $stats['total_users'] }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ trans('bot-owner.total_users') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $stats['pending_admin_kie'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}">
                            {{ $stats['pending_admin_kie'] }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ trans('bot-owner.pending_admin_requests') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $stats['pending_plan_requests'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}">
                            {{ $stats['pending_plan_requests'] }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ trans('bot-owner.pending_plan_requests') }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold {{ $stats['pending_uploads'] > 0 ? 'text-yellow-600' : 'text-gray-900' }}">
                            {{ $stats['pending_uploads'] }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">{{ trans('bot-owner.pending_uploads') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Sections -->
        <h2 class="text-lg font-semibold mb-4">{{ trans('bot-owner.management_sections') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($sections as $section)
                <a href="{{ $section['route'] }}" 
                   class="block bg-white p-4 rounded shadow hover:shadow-md transition border border-gray-100 hover:border-blue-200">
                    <h3 class="font-medium text-gray-900">{{ trans('bot-owner.' . $section['title_key']) }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.' . $section['description_key']) }}</p>
                </a>
            @endforeach
        </div>
    </main>
</div>
@endsection
