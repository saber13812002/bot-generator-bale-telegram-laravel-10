<!-- Book Library Bot - Specific Settings -->

<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Manage your book library bot's content structure, user plans, and media files.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Plan Management Link -->
        <a href="{{ route('bot-owner.manage.plans', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.plan_title') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_plans_desc') }}</p>
        </a>

        <!-- Categories Management Link -->
        <a href="{{ route('bot-owner.manage.categories', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_categories') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_categories_desc') }}</p>
        </a>

        <!-- Content Items Link -->
        <a href="{{ route('bot-owner.manage.items', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_items') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_items_desc') }}</p>
        </a>

        <!-- Pending Uploads Link -->
        <a href="{{ route('bot-owner.manage.uploads', $bot->id) }}" 
           class="block p-4 border border-gray-200 rounded-lg hover:border-blue-300 hover:bg-blue-50 transition">
            <h4 class="font-medium text-gray-900">{{ trans('bot-owner.section_uploads') }}</h4>
            <p class="text-sm text-gray-500 mt-1">{{ trans('bot-owner.section_uploads_desc') }}</p>
        </a>
    </div>
</div>
