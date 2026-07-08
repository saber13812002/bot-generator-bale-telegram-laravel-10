<!-- Content Submission Bot - Specific Settings -->

<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Manage content submissions, approval queues, and channel configuration.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Category Management Link -->
        <a href="{{ route('bot-owner.manage.categories', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_categories') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_categories_desc') }}</p>
        </a>

        <!-- Pending Uploads Link -->
        <a href="{{ route('bot-owner.manage.uploads', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_uploads') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_uploads_desc') }}</p>
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
            ⚡ Content Submission bot approval workflow: Submitted content goes through an approval group before publishing.
            Manage categories and uploaded files here.
        </p>
    </div>
</div>
