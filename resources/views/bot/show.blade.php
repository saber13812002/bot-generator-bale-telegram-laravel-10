<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $bot->name }} - ربات‌های پردیسانیا</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="antialiased bg-gray-50 dark:bg-gray-900">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <a href="{{ url('/') }}" class="flex items-center space-x-2 text-gray-600 dark:text-gray-300 hover:text-red-500 dark:hover:text-red-400 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        <span>بازگشت به صفحه اصلی</span>
                    </a>
                    <div class="flex items-center space-x-2">
                        @if($bot->icon_svg)
                            <div class="h-10 w-10">{!! $bot->icon_svg !!}</div>
                        @else
                            <span class="text-3xl">{{ $bot->icon_emoji ?? '🤖' }}</span>
                        @endif
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $bot->name }}</h1>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Image Section -->
                    @if($bot->image || $bot->image_url || $bot->image_path)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                            <img src="{{ $bot->image }}" 
                                 alt="{{ $bot->name }}" 
                                 class="w-full h-64 object-cover">
                        </div>
                    @endif

                    <!-- Description Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">توضیحات</h2>
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed">
                            {{ $bot->description ?? 'بدون توضیحات' }}
                        </p>
                        @if($bot->detailed_description)
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <p class="text-gray-700 dark:text-gray-200 leading-relaxed whitespace-pre-line">
                                    {{ $bot->detailed_description }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Features Section -->
                    @if($bot->features && count($bot->features) > 0)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">ویژگی‌ها</h2>
                            <ul class="space-y-3">
                                @foreach($bot->features as $key => $value)
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $key }}:</span>
                                            <span class="text-gray-600 dark:text-gray-300 ml-2">{{ $value }}</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Usage Instructions Section -->
                    @if($bot->usage_instructions)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">دستورالعمل استفاده</h2>
                            <div class="text-gray-700 dark:text-gray-200 leading-relaxed whitespace-pre-line">
                                {{ $bot->usage_instructions }}
                            </div>
                        </div>
                    @endif

                    <!-- Technical Details Section -->
                    @if($bot->technical_details)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">جزئیات فنی</h2>
                            <div class="text-gray-700 dark:text-gray-200 leading-relaxed whitespace-pre-line">
                                {{ $bot->technical_details }}
                            </div>
                        </div>
                    @endif

                    <!-- Links Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">لینک‌های نمونه</h2>
                        <div class="flex flex-wrap gap-4 mb-4">
                            @if($bot->sample_telegram_link)
                                <a href="{{ $bot->sample_telegram_link }}" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                    </svg>
                                    Telegram
                                </a>
                            @endif
                            @if($bot->sample_bale_link)
                                <a href="{{ $bot->sample_bale_link }}" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.14.18-.357.295-.6.295-.002 0-.003 0-.005 0l.213-3.054 5.56-5.022c.24-.213-.054-.334-.373-.12l-6.87 4.326-2.96-.924c-.64-.203-.658-.64.135-.954l11.566-4.458c.538-.196 1.006.128.832.941z"/>
                                    </svg>
                                    Bale
                                </a>
                            @endif
                            @if($bot->blog_virgool_link)
                                <a href="{{ $bot->blog_virgool_link }}" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition">
                                    📝 ویرگول
                                </a>
                            @endif
                            @if($bot->blog_medium_link)
                                <a href="{{ $bot->blog_medium_link }}" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 transition">
                                    📝 Medium
                                </a>
                            @endif
                        </div>
                        @if(!in_array($bot->endpoint_id, ['admin-bots', 'get-chat-id']))
                        <a href="{{ route('bot-owner.create', $bot->endpoint_id) }}"
                           class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                            + ساختن این ربات
                        </a>
                        @endif
                    </div>
                </div>

                <!-- Right Column: Sidebar -->
                <div class="space-y-6">
                    <!-- Bot Info Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">اطلاعات ربات</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">شناسه:</span>
                                <span class="text-gray-900 dark:text-white font-mono mr-2">{{ $bot->endpoint_id }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Route:</span>
                                <span class="text-gray-900 dark:text-white font-mono mr-2">{{ $bot->route }}</span>
                            </div>
                            @if($bot->requires_bot_mother_id)
                                <div class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600 dark:text-gray-300">نیاز به Bot Mother ID</span>
                                </div>
                            @endif
                            @if($bot->requires_token)
                                <div class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600 dark:text-gray-300">نیاز به Token</span>
                                </div>
                            @endif
                            @if($bot->supports_multiple_languages)
                                <div class="flex items-center">
                                    <span class="text-green-500 mr-2">✓</span>
                                    <span class="text-gray-600 dark:text-gray-300">پشتیبانی از چند زبان</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Related Bots Section -->
                    @if($relatedBots->count() > 0)
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">ربات‌های مرتبط</h3>
                            <div class="space-y-4">
                                @foreach($relatedBots as $relatedBot)
                                    <a href="{{ route('bot.show', $relatedBot->endpoint_id) }}" 
                                       class="block p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition">
                                        <div class="flex items-center space-x-3">
                                            @if($relatedBot->icon_svg)
                                                <div class="h-10 w-10 flex-shrink-0">{!! $relatedBot->icon_svg !!}</div>
                                            @else
                                                <span class="text-2xl">{{ $relatedBot->icon_emoji ?? '🤖' }}</span>
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                    {{ $relatedBot->name }}
                                                </h4>
                                                @if($relatedBot->description)
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-1">
                                                        {{ $relatedBot->description }}
                                                    </p>
                                                @endif
                                            </div>
                                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="text-center text-gray-600 dark:text-gray-400">
                    <p>&copy; {{ date('Y') }} ربات‌های پردیسانیا. تمامی حقوق محفوظ است.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
