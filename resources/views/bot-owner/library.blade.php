@extends('layouts.web')

@section('title', trans('bot-owner.library_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.library_title') }}</h1>
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

        <!-- Filter Section -->
        <div class="bg-white p-4 rounded shadow mb-6">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-sm font-medium text-gray-700">{{ trans('bot-owner.filter_by_type') }}:</span>
                
                @if($currentType)
                    <a href="{{ route('bot-owner.library') }}" 
                       class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 rounded-full text-sm hover:bg-red-200 transition">
                        {{ trans('bot-owner.clear_filter') }}
                    </a>
                @endif

                <div class="flex flex-wrap gap-2">
                    @foreach($botTypes as $type)
                        <a href="{{ route('bot-owner.library', ['type' => $type['id']]) }}"
                           class="px-3 py-1.5 rounded-full text-sm transition
                           @if($currentType === $type['id'])
                               bg-blue-600 text-white
                           @else
                               bg-gray-100 text-gray-700 hover:bg-gray-200
                           @endif">
                            {{ $type['name'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Bots List -->
        <h2 class="text-lg font-semibold mb-4">{{ trans('bot-owner.my_bots') }} ({{ $bots->total() }})</h2>
        
        @if($bots->isEmpty())
            <div class="bg-white p-8 rounded shadow text-center">
                <p class="text-gray-500">{{ trans('bot-owner.no_bots_found') }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($bots as $bot)
                    <a href="{{ route('bot-owner.manage', $bot->id) }}" 
                       class="block bg-white p-4 rounded shadow hover:shadow-md transition border border-gray-100 hover:border-blue-200">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ $bot->bale_bot_name ?? $bot->telegram_bot_name ?? __('Bot #:id', ['id' => $bot->id]) }}
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    @if($bot->webhookEndpoint)
                                        <span class="inline-block bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-xs ml-2">
                                            {{ $bot->webhookEndpoint->name }}
                                        </span>
                                    @endif
                                    <span>{{ $bot->endpoint_id }}</span>
                                    <span class="mx-1">—</span>
                                    <span>{{ $bot->type ?? '—' }}</span>
                                    @if($bot->language_code)
                                        <span class="mx-1">—</span>
                                        <span>{{ $bot->language_code }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs px-2 py-1 rounded
                                    @if(($bot->bale_bot_status ?? $bot->telegram_bot_status) === 'Active')
                                        bg-green-100 text-green-700
                                    @else
                                        bg-gray-100 text-gray-500
                                    @endif">
                                    {{ $bot->bale_bot_status ?? $bot->telegram_bot_status ?? '—' }}
                                </span>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $bots->links() }}
            </div>
        @endif
    </main>
</div>
@endsection
