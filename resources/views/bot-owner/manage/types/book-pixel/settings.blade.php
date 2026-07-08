<!-- Book Pixel Bot - Specific Settings -->

<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Manage book page sharing, content moderation, and page categories.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Category Management Link -->
        <a href="{{ route('bot-owner.manage.categories', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_categories') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_categories_desc') }}</p>
        </a>

        <!-- Pending Uploads Link (for page approval) -->
        <a href="{{ route('bot-owner.manage.uploads', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_uploads') }}</h4>
            <p class="text-sm text-gray-500 mt-1">Approve or reject book page submissions</p>
        </a>

        <!-- Content Items Link -->
        <a href="{{ route('bot-owner.manage.items', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_items') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_items_desc') }}</p>
        </a>
    </div>

    <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <p class="text-sm text-yellow-800">
            📸 Book Pixel shares book pages one by one. Use the uploads section to review and approve
            page submissions, then assign them to categories.
        </p>
    </div>
</div>
