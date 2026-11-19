@extends('layouts.web')

@section('title', trans('bot-owner.stats_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.stats_title') }}</h1>
            <div class="flex gap-4 items-center">
                <a href="{{ route('bot-owner.manage', $bot->id) }}" class="text-sm text-gray-600 hover:underline">
                    ← {{ trans('bot-owner.back_to_manage') }}
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">
        <div class="bg-white rounded shadow overflow-hidden">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">{{ $bot->bale_bot_name ?? $bot->telegram_bot_name ?? __('Bot #:id', ['id' => $bot->id]) }}</h2>
                
                @if(empty(array_filter($stats)))
                    <p class="text-gray-500">{{ trans('bot-owner.no_stats_available') }}</p>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        @foreach($stats as $key => $value)
                            @if($value > 0 || $key === 'total_users')
                                <div class="bg-gray-50 rounded p-4 text-center">
                                    <div class="text-3xl font-bold text-gray-900">{{ $value }}</div>
                                    <div class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.' . $key) }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </main>
</div>
@endsection
