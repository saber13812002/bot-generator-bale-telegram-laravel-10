@extends('layouts.web')

@section('title', trans('bot-owner.login_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center" dir="rtl">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold text-center mb-6">{{ trans('bot-owner.login_title') }}</h1>
        <p class="text-sm text-gray-500 text-center mb-6">{{ trans('bot-owner.login_description') }}</p>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('bot-owner.otp.send') }}" class="mb-4">
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirect ?? route('bot-owner.dashboard') }}">
            <label class="block text-sm font-medium mb-1">{{ trans('bot-owner.phone_label') }}</label>
            <input type="tel" name="phone" value="{{ old('phone') }}" required
                   placeholder="09123456789"
                   class="w-full border rounded px-3 py-2 mb-3">
            <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700">
                {{ trans('bot-owner.send_otp') }}
            </button>
        </form>

        @if(session('otp_sent') || old('phone'))
        <form method="POST" action="{{ route('bot-owner.otp.verify') }}">
            @csrf
            <input type="hidden" name="phone" value="{{ old('phone') }}">
            <input type="hidden" name="redirect" value="{{ $redirect ?? route('bot-owner.dashboard') }}">
            <label class="block text-sm font-medium mb-1">{{ trans('bot-owner.otp_label') }}</label>
            <input type="text" name="otp" required maxlength="8"
                   class="w-full border rounded px-3 py-2 mb-3" placeholder="123456">
            <button type="submit" class="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700">
                {{ trans('bot-owner.verify_otp') }}
            </button>
        </form>
        @endif

        <p class="text-xs text-gray-400 mt-6 text-center">
            <a href="{{ route('bot-owner.intro') }}" class="hover:underline">{{ trans('bot-owner.back_to_intro') }}</a>
        </p>
    </div>
</div>
@endsection
