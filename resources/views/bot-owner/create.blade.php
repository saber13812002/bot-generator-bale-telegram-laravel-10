@extends('layouts.web')

@section('title', trans('bot-owner.create_title', ['name' => $endpoint->name]))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-3xl mx-auto px-4 py-4">
            <a href="{{ route('bot-owner.dashboard') }}" class="text-gray-600 hover:text-red-600 text-sm">
                ← {{ trans('bot-owner.back_to_dashboard') }}
            </a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-2">{{ trans('bot-owner.create_title', ['name' => $endpoint->name]) }}</h1>
        <p class="text-gray-600 mb-6">{{ $endpoint->description }}</p>

        @if($endpoint->usage_instructions)
            <div class="bg-blue-50 border border-blue-200 rounded p-4 mb-6 text-sm whitespace-pre-line">
                {{ $endpoint->usage_instructions }}
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600 mb-4">{{ trans('bot-owner.botfather_hint') }}</p>

            <form method="POST" action="{{ route('bot-owner.create.store', $endpoint->endpoint_id) }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">{{ trans('bot-owner.platform_label') }}</label>
                    <select name="platform" class="w-full border rounded px-3 py-2" required>
                        @foreach($platforms as $platform)
                            <option value="{{ $platform }}">{{ $platform === 'bale' ? 'بله' : 'تلگرام' }}</option>
                        @endforeach
                    </select>
                </div>

                @if($endpoint->requires_language)
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">{{ trans('bot-owner.language_label') }}</label>
                    <select name="language" class="w-full border rounded px-3 py-2">
                        <option value="fa">فارسی</option>
                        <option value="en">English</option>
                        <option value="ar">العربية</option>
                    </select>
                </div>
                @else
                    <input type="hidden" name="language" value="fa">
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">{{ trans('bot-owner.token_label') }}</label>
                    <input type="text" name="token" required class="w-full border rounded px-3 py-2 font-mono text-sm"
                           placeholder="1234567890:ABCDEF...">
                </div>

                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700">
                    {{ trans('bot-owner.submit_create') }}
                </button>
            </form>
        </div>
    </main>
</div>
@endsection
