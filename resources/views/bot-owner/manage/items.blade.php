@extends('layouts.web')

@section('title', trans('bot-owner.items_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.items_title') }}</h1>
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
            @if($items->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-gray-500">{{ trans('bot-owner.items_empty') }}</p>
                    <p class="text-sm text-gray-400 mt-2">{{ trans('bot-owner.items_select_category') }}</p>
                </div>
            @else
                <!-- Items grouped by category -->
                @php
                    $grouped = $items->groupBy(function($item) {
                        return $item->category ? $item->category->title : 'Uncategorized';
                    });
                @endphp

                @foreach($grouped as $categoryTitle => $categoryItems)
                    <div class="border-b last:border-b-0">
                        <div class="px-4 py-3 bg-gray-50 border-b">
                            <h3 class="font-medium text-gray-700">{{ $categoryTitle }} ({{ $categoryItems->count() }})</h3>
                        </div>
                        
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="text-right px-4 py-2 font-medium text-gray-600">{{ trans('bot-owner.items_reorder') }}</th>
                                    <th class="text-right px-4 py-2 font-medium text-gray-600">{{ trans('bot-owner.items_item') }}</th>
                                    <th class="text-right px-4 py-2 font-medium text-gray-600">{{ trans('bot-owner.items_asset') }}</th>
                                    <th class="text-center px-4 py-2 font-medium text-gray-600">{{ trans('bot-owner.category_status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y sortable-items" data-category="{{ $categoryItems->first()->category_id }}">
                                @foreach($categoryItems as $item)
                                    <tr class="hover:bg-gray-50" data-id="{{ $item->id }}">
                                        <td class="px-4 py-2 text-gray-500 w-24">
                                            <span class="text-xs text-gray-400">{{ $item->queue_order }}</span>
                                            <button type="button" class="move-item-up text-xs text-gray-500 hover:text-blue-600 ml-1">↑</button>
                                            <button type="button" class="move-item-down text-xs text-gray-500 hover:text-blue-600">↓</button>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="font-medium">{{ $item->title ?? '—' }}</div>
                                            @if($item->description)
                                                <div class="text-xs text-gray-400 truncate max-w-xs">{{ $item->description }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            @foreach($item->assets as $asset)
                                                <span class="inline-block px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-600 ml-1">
                                                    {{ $asset->type }}
                                                </span>
                                            @endforeach
                                            @if($item->assets->isEmpty())
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="text-xs px-2 py-0.5 rounded
                                                @if($item->is_active) bg-green-100 text-green-700 @else bg-gray-100 text-gray-500 @endif">
                                                {{ $item->is_active ? trans('bot-owner.category_active') : trans('bot-owner.category_inactive') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach

                <!-- Reorder Form -->
                <div class="p-4 border-t bg-gray-50">
                    <form method="POST" action="{{ route('bot-owner.manage.items.reorder', $bot->id) }}" id="items-reorder-form">
                        @csrf
                        <input type="hidden" name="order" id="items-reorder-input" value="">
                        <button type="submit" id="save-items-order-btn" 
                                class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition hidden">
                            {{ trans('bot-owner.items_reorder') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </main>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sortable-items').forEach(tbody => {
        const rows = Array.from(tbody.querySelectorAll('tr'));

        tbody.addEventListener('click', function(e) {
            const btn = e.target.closest('.move-item-up, .move-item-down');
            if (!btn) return;

            const row = btn.closest('tr');
            if (btn.classList.contains('move-item-up')) {
                const prev = row.previousElementSibling;
                if (prev) tbody.insertBefore(row, prev);
            } else if (btn.classList.contains('move-item-down')) {
                const next = row.nextElementSibling;
                if (next) tbody.insertBefore(next, row);
            }

            updateItemsReorder();
        });
    });

    function updateItemsReorder() {
        const order = [];
        document.querySelectorAll('.sortable-items tr').forEach((row, idx) => {
            order.push({ id: parseInt(row.dataset.id), queue_order: idx + 1 });
        });
        document.getElementById('items-reorder-input').value = JSON.stringify(order);
        document.getElementById('save-items-order-btn').classList.remove('hidden');
    }
});
</script>
@endpush
@endsection
