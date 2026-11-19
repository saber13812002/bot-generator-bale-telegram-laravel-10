@extends('layouts.web')

@section('title', trans('bot-owner.uploads_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.uploads_title') }}</h1>
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
            @if($uploads->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-gray-500">{{ trans('bot-owner.uploads_empty') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.uploads_file') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.uploads_uploaded_by') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.uploads_origin') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.uploads_date') }}</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.bot_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($uploads as $upload)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $upload->title ?? $upload->file_unique_id ?? 'File #'.$upload->id }}</div>
                                        @if($upload->mime_type)
                                            <div class="text-xs text-gray-400">{{ $upload->mime_type }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $upload->uploaded_by_chat_id }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-600">
                                            {{ $upload->origin }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $upload->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-2 items-center">
                                            @if($categories->isNotEmpty())
                                                <form method="POST" action="{{ route('bot-owner.manage.uploads.approve', [$bot->id, $upload->id]) }}" 
                                                      class="flex items-center gap-2">
                                                    @csrf
                                                    <select name="category_id" required 
                                                            class="border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:border-blue-400">
                                                        <option value="">{{ trans('bot-owner.uploads_select_category') }}</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->title }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700 transition">
                                                        {{ trans('bot-owner.uploads_approve') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-xs text-gray-400">{{ trans('bot-owner.uploads_select_category') }}</span>
                                            @endif
                                            
                                            <form method="POST" action="{{ route('bot-owner.manage.uploads.reject', [$bot->id, $upload->id]) }}"
                                                  onsubmit="return confirm('Are you sure?')">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700 transition">
                                                    {{ trans('bot-owner.uploads_reject') }}
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
