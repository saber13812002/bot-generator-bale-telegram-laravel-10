@extends('layouts.web')

@section('title', trans('bot-owner.category_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.category_title') }}</h1>
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

        <!-- Add Category Form -->
        <div class="bg-white rounded shadow p-6 mb-6">
            <h3 class="font-medium mb-4">{{ trans('bot-owner.category_add') }}</h3>
            <form method="POST" action="{{ route('bot-owner.manage.categories.store', $bot->id) }}" class="flex gap-3">
                @csrf
                <input type="text" name="title" required 
                       placeholder="{{ trans('bot-owner.category_name_placeholder') }}"
                       class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-400">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition">
                    {{ trans('bot-owner.category_add') }}
                </button>
            </form>
        </div>

        <!-- Categories List -->
        <div class="bg-white rounded shadow overflow-hidden">
            @if($categories->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-gray-500">{{ trans('bot-owner.category_empty') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.category_sort_order') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.category_name') }}</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.category_items') }}</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.category_status') }}</th>
                                <th class="text-center px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.bot_status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" id="category-sortable">
                            @foreach($categories as $category)
                                <tr class="hover:bg-gray-50" data-id="{{ $category->id }}">
                                    <td class="px-4 py-3 text-gray-500">
                                        <span class="sort-handle cursor-grab text-gray-400 mr-2">⠿</span>
                                        {{ $category->sort_order }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('bot-owner.manage.categories.update', [$bot->id, $category->id]) }}" 
                                              class="flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="title" value="{{ $category->title }}"
                                                   class="border border-gray-200 rounded px-2 py-1 text-sm flex-1 focus:outline-none focus:border-blue-400">
                                            <button type="submit" class="text-xs text-blue-600 hover:underline">{{ trans('bot-owner.category_edit') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-500">
                                        {{ $category->items_count }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" action="{{ route('bot-owner.manage.categories.update', [$bot->id, $category->id]) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="title" value="{{ $category->title }}">
                                            <button type="submit" name="is_active" value="{{ $category->is_active ? 0 : 1 }}"
                                                    class="text-xs px-2 py-1 rounded
                                                    @if($category->is_active) bg-green-100 text-green-700 @else bg-gray-100 text-gray-500 @endif">
                                                {{ $category->is_active ? trans('bot-owner.category_active') : trans('bot-owner.category_inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <form method="POST" action="{{ route('bot-owner.manage.categories.destroy', [$bot->id, $category->id]) }}"
                                              onsubmit="return confirm('{{ trans('bot-owner.category_delete_confirm') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">{{ trans('bot-owner.category_delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Reorder Form -->
                <div class="p-4 border-t bg-gray-50">
                    <form method="POST" action="{{ route('bot-owner.manage.categories.reorder', $bot->id) }}" id="reorder-form">
                        @csrf
                        <input type="hidden" name="order" id="reorder-input" value="">
                        <button type="submit" id="save-order-btn" 
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
    const tbody = document.getElementById('category-sortable');
    if (!tbody) return;

    let rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Simple up/down reorder buttons
    rows.forEach((row, index) => {
        const sortHandle = row.querySelector('.sort-handle');
        if (!sortHandle) return;
        
        // Add up/down buttons after the sort handle
        const actions = document.createElement('span');
        actions.className = 'inline-flex gap-1 mr-1';
        actions.innerHTML = `
            <button type="button" class="move-up text-xs text-gray-500 hover:text-blue-600 ${index === 0 ? 'opacity-30 cursor-not-allowed' : ''}" ${index === 0 ? 'disabled' : ''}>↑</button>
            <button type="button" class="move-down text-xs text-gray-500 hover:text-blue-600 ${index === rows.length - 1 ? 'opacity-30 cursor-not-allowed' : ''}" ${index === rows.length - 1 ? 'disabled' : ''}>↓</button>
        `;
        sortHandle.parentNode.insertBefore(actions, sortHandle.nextSibling);
    });

    // Move up/down logic
    tbody.addEventListener('click', function(e) {
        const btn = e.target.closest('.move-up, .move-down');
        if (!btn) return;

        const row = btn.closest('tr');
        if (btn.classList.contains('move-up')) {
            const prev = row.previousElementSibling;
            if (prev) {
                tbody.insertBefore(row, prev);
            }
        } else if (btn.classList.contains('move-down')) {
            const next = row.nextElementSibling;
            if (next) {
                tbody.insertBefore(next, row);
            }
        }

        updateReorder();
    });

    function updateReorder() {
        const currentRows = tbody.querySelectorAll('tr');
        const order = [];
        currentRows.forEach((row, idx) => {
            order.push({ id: parseInt(row.dataset.id), sort_order: idx + 1 });
        });
        document.getElementById('reorder-input').value = JSON.stringify(order);
        document.getElementById('save-order-btn').classList.remove('hidden');
    }
});
</script>
@endpush
@endsection
