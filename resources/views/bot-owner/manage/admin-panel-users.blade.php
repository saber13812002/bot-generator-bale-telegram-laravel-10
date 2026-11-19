@extends('layouts.web')

@section('title', trans('bot-owner.admin_panel_users_title'))

@push('styles')
<script src="https://cdn.tailwindcss.com"></script>
@endpush

@section('content')
<div class="min-h-screen bg-gray-50" dir="rtl">
    <header class="bg-white shadow-sm">
        <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-bold">{{ trans('bot-owner.admin_panel_users_title') }}</h1>
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

        <!-- Add Admin Form (only owner can add) -->
        @if($isOwner)
            <div class="bg-white rounded shadow p-6 mb-6">
                <h3 class="font-medium mb-4">{{ trans('bot-owner.admin_panel_users_add') }}</h3>
                <form method="POST" action="{{ route('bot-owner.manage.admin-panel-users.store', $bot->id) }}" class="flex gap-3" id="add-admin-form">
                    @csrf
                    <div class="flex-1 relative">
                        <input type="text" id="owner-search" 
                               placeholder="{{ trans('bot-owner.admin_panel_users_add_placeholder') }}"
                               class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:border-blue-400"
                               autocomplete="off">
                        <input type="hidden" name="bot_owner_id" id="selected-owner-id">
                        <div id="search-results" class="absolute top-full left-0 right-0 bg-white border border-gray-200 rounded mt-1 shadow-lg z-10 hidden"></div>
                    </div>
                    <button type="submit" id="add-admin-btn" disabled
                            class="px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ trans('bot-owner.admin_panel_users_add') }}
                    </button>
                </form>
            </div>
        @endif

        <!-- Admins List -->
        <div class="bg-white rounded shadow overflow-hidden">
            @if($admins->isEmpty())
                <div class="p-8 text-center">
                    <p class="text-gray-500">{{ trans('bot-owner.admin_panel_users_empty') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.admin_panel_users_phone') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.admin_panel_users_added_by') }}</th>
                                <th class="text-right px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.admin_panel_users_added_at') }}</th>
                                @if($isOwner)
                                    <th class="text-center px-4 py-3 font-medium text-gray-600">{{ trans('bot-owner.bot_status') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($admins as $adminUser)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium">{{ $adminUser->botOwner->phone }}</div>
                                        @if($adminUser->botOwner->name)
                                            <div class="text-xs text-gray-500">{{ $adminUser->botOwner->name }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $adminUser->addedBy->phone ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">
                                        {{ $adminUser->created_at->format('Y-m-d H:i') }}
                                    </td>
                                    @if($isOwner)
                                        <td class="px-4 py-3 text-center">
                                            <form method="POST" action="{{ route('bot-owner.manage.admin-panel-users.destroy', [$bot->id, $adminUser->id]) }}"
                                                  onsubmit="return confirm('{{ trans('bot-owner.admin_panel_users_remove_confirm') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 hover:underline">
                                                    {{ trans('bot-owner.admin_panel_users_remove') }}
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </main>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('owner-search');
    const resultsDiv = document.getElementById('search-results');
    const selectedId = document.getElementById('selected-owner-id');
    const addBtn = document.getElementById('add-admin-btn');

    if (!searchInput) return;

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const q = this.value.trim();

        if (q.length < 2) {
            resultsDiv.classList.add('hidden');
            selectedId.value = '';
            addBtn.disabled = true;
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch(`{{ route('bot-owner.admin-panel-users.search') }}?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length === 0) {
                        resultsDiv.innerHTML = '<div class="px-3 py-2 text-sm text-gray-500">{{ trans('bot-owner.admin_panel_search_no_results') }}</div>';
                    } else {
                        data.forEach(owner => {
                            const div = document.createElement('div');
                            div.className = 'px-3 py-2 text-sm hover:bg-blue-50 cursor-pointer border-b last:border-b-0';
                            div.textContent = `${owner.phone}${owner.name ? ' — ' + owner.name : ''}`;
                            div.dataset.id = owner.id;
                            div.addEventListener('click', function() {
                                searchInput.value = this.textContent;
                                selectedId.value = this.dataset.id;
                                addBtn.disabled = false;
                                resultsDiv.classList.add('hidden');
                            });
                            resultsDiv.appendChild(div);
                        });
                    }
                    resultsDiv.classList.remove('hidden');
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#add-admin-form')) {
            resultsDiv.classList.add('hidden');
        }
    });
});
</script>
@endpush
@endsection
