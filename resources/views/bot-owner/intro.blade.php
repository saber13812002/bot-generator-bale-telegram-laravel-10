@extends('layouts.web')

@section('title', trans('bot-owner.intro_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <a href="{{ url('/') }}" class="text-gray-600 hover:text-red-600">{{ trans('bot-owner.back_to_catalog') }}</a>
            <a href="https://pardisania.ir" target="_blank" class="text-gray-600 hover:text-red-600">{{ trans('bot-owner.pardisania_home') }}</a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-10">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded">{{ session('error') }}</div>
        @endif

        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ trans('bot-owner.intro_title') }}</h1>
            <p class="text-gray-600 max-w-2xl mx-auto">{{ trans('bot-owner.intro_description') }}</p>
            <div class="mt-6 flex gap-4 justify-center">
                @if($owner)
                    <a href="{{ route('bot-owner.dashboard') }}" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        {{ trans('bot-owner.go_to_dashboard') }}
                    </a>
                @else
                    <a href="{{ route('bot-owner.login') }}" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        {{ trans('bot-owner.start_login') }}
                    </a>
                @endif
                <a href="{{ url('/') }}" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-100">
                    {{ trans('bot-owner.explore_bots') }}
                </a>
            </div>
        </div>

        <h2 class="text-xl font-semibold mb-4">{{ trans('bot-owner.available_bot_types') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($endpoints as $endpoint)
                <div class="bg-white p-5 rounded-lg shadow">
                    <h3 class="font-semibold text-lg">{{ $endpoint->name }}</h3>
                    <p class="text-sm text-gray-500 mt-2">{{ $endpoint->description }}</p>
                    <div class="mt-4 flex gap-2">
                        <a href="{{ route('bot.show', $endpoint->endpoint_id) }}" class="text-sm text-blue-600 hover:underline">
                            {{ trans('bot-owner.more_info') }}
                        </a>
                        <a href="{{ route('bot-owner.create', $endpoint->endpoint_id) }}" class="text-sm text-red-600 hover:underline">
                            + {{ trans('bot-owner.create_bot') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </main>
</div>
@endsection
