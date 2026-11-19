@extends('layouts.web')

@section('title', trans('bot-owner.plan_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.plan_title') }}</h1>
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

        <div class="bg-white rounded shadow overflow-hidden">
            @if($requests->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-gray-500">{{ trans('bot-owner.plan_no_pending') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.plan_user') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.plan_name') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.plan_payment_method') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.plan_payment_info') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.plan_requested_at') }}</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.bot_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($requests as $planReq)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">
                                            @if($planReq->botUser)
                                                {{ $planReq->botUser->first_name ?? $planReq->botUser->chat_id }}
                                            @else
                                                ID: {{ $planReq->bot_user_id }}
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
                                            {{ $planReq->plan }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $planReq->payment_method ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 max-w-xs truncate">
                                        {{ $planReq->payment_info ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $planReq->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center gap-2">
                                            <form method="POST" action="{{ route('bot-owner.manage.plans.approve', [$bot->id, $planReq->id]) }}">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 bg-green-600 text-white rounded text-xs hover:bg-green-700 transition">
                                                    {{ trans('bot-owner.plan_approve') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('bot-owner.manage.plans.reject', [$bot->id, $planReq->id]) }}"
                                                  onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition">
                                                    {{ trans('bot-owner.plan_reject') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </main>
</div>
@endsection
