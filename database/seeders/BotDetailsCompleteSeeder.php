<?php

namespace Database\Seeders;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class BotDetailsCompleteSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. QURAN BOT
        // ==========================================
        $this->updateDetails('quran-bot', [
            'icon_emoji' => '📖',
            'detailed_description' => "Complete Quran study bot with 18 languages, audio recitations, memorization system, and full-text search.",
            'features' => [
                'Verse-by-verse study' => 'Arabic + translation + tafsir',
                'Audio recitation' => 'Arabic & Persian (MP3)',
                'Full-text search' => 'Across entire Quran',
                'Languages' => '18 living languages',
                'Memorization' => 'Smart repetition system',
                'Khatm Quran' => 'Personalized scheduling',
                'Scanned pages' => 'Page-by-page display',
            ],
            'usage_instructions' => "1. Send /start\n2. Select a surah from the list\n3. Send verse number or use buttons\n4. Options: text, translation, audio, tafsir\n5. /search <keyword> for search\n6. /memorize for memorization mode",
            'image_path' => 'images/bots/quran-bot.png',
        ]);

        // ==========================================
        // 2. WEATHER BOT
        // ==========================================
        $this->updateDetails('weather-bot', [
            'icon_emoji' => '🌤️',
            'detailed_description' => "Weather forecast bot with 16-hour predictions, customizable temperature/wind alerts, and group broadcast.",
            'features' => [
                'Forecast' => '16-hour prediction',
                'Temperature alerts' => 'Customizable thresholds',
                'Wind alerts' => 'Speed-based alerts',
                'Group broadcast' => 'Auto-send to groups',
                'Rain alerts' => 'Precipitation probability',
            ],
            'usage_instructions' => "1. Send /start\n2. Send city name\n3. 16-hour forecast displayed\n4. /alert to set custom alerts",
            'image_path' => 'images/bots/weather-bot.png',
        ]);

        // ==========================================
        // 3. HADITH BOT
        // ==========================================
        $this->updateDetails('hadith-bot', [
            'icon_emoji' => '📜',
            'detailed_description' => "Advanced search in authentic Shia Hadith books with full-text indexing and chain of narration display.",
            'features' => [
                'Full-text search' => 'All books indexed',
                'Sources' => 'Authentic Shia Hadith books',
                'Chain display' => 'Complete narration chain',
                'Random hadith' => 'Daily random hadith',
                'History' => 'Search history',
            ],
            'usage_instructions' => "1. Send /start\n2. Type keyword to search\n3. Browse results\n4. Send number for full text\n5. /random for random hadith",
            'image_path' => 'images/bots/hadith-bot.png',
        ]);

        // ==========================================
        // 4. NAHJ BOT
        // ==========================================
        $this->updateDetails('nahj-bot', [
            'icon_emoji' => '📚',
            'detailed_description' => "Complete Nahj al-Balagha explorer - sermons, letters, and wisdoms of Imam Ali (AS) with full-text search.",
            'features' => [
                'Sermons' => 'All 241 sermons',
                'Letters' => 'All 79 letters',
                'Wisdom' => 'All 480 wisdoms',
                'Search' => 'Full-text across all',
            ],
            'usage_instructions' => "1. Send /start\n2. Search or browse categories\n3. Choose sermon/letter/wisdom\n4. Navigate with buttons",
            'image_path' => 'images/bots/nahj-bot.png',
        ]);

        // ==========================================
        // 5. PRAYER QADHA BOT
        // ==========================================
        $this->updateDetails('prayer-bot', [
            'icon_emoji' => '🕌',
            'detailed_description' => "Missed prayers tracker with smart prayer type detection, weekly email reports, and web dashboard.",
            'features' => [
                'Smart detection' => 'Auto-detect prayer type',
                'Record keeping' => '2, 3, 4 rakats',
                'Email reports' => 'Weekly statistics',
                'Web dashboard' => 'Online report page',
                'Estimate mode' => 'Quick estimate entry',
                'Unsubscribe' => 'Email opt-out',
            ],
            'usage_instructions' => "1. Send /start\n2. Send rakat count (2, 3, or 4)\n3. Bot auto-detects prayer type\n4. /report for statistics\n5. /estimate for quick entry\n6. Register email for weekly report",
            'image_path' => 'images/bots/prayer-bot.png',
        ]);

        // ==========================================
        // 6. MAWKIB FINDER
        // ==========================================
        $this->updateDetails('mawkib-finder', [
            'icon_emoji' => '🕋',
            'detailed_description' => "Arbaeen procession finder with Bale OTP authentication, province selection, date picker, and real-time availability.",
            'features' => [
                'OTP Auth' => 'Bale phone verification',
                'Province selection' => 'All 31 provinces',
                'Date picker' => 'Next 14 days',
                'Stay duration' => '1-14 days',
                'Capacity display' => 'Real-time availability',
                'Registration link' => 'Final registration',
            ],
            'usage_instructions' => "1. /start\n2. Enter phone number\n3. Enter OTP code\n4. Enter national ID (10 digits)\n5. Select province\n6. Select entry date\n7. Select stay duration\n8. View available processions",
            'image_path' => 'images/bots/mawkib-finder.png',
        ]);

        // ==========================================
        // 7. PRESENTER BOT
        // ==========================================
        $this->updateDetails('presenter-bot', [
            'icon_emoji' => '🎤',
            'detailed_description' => "Sequential content delivery bot for courses and presentations. Supports text, images, videos, voice, and documents.",
            'features' => [
                'Sequential delivery' => 'Next/previous navigation',
                'Multi-format' => 'Text, photo, video, voice',
                'Progress tracking' => 'Shows position in queue',
                'Reset option' => 'Clear and restart',
            ],
            'usage_instructions' => "1. After creation, add content items\n2. User sends /start\n3. First item displayed\n4. /next for next item\n5. 'end' to finish adding content",
            'image_path' => 'images/bots/presenter-bot.png',
        ]);

        // ==========================================
        // 8. RATING BOT
        // ==========================================
        $this->updateDetails('rating-bot', [
            'icon_emoji' => '⭐',
            'detailed_description' => "Survey and rating bot - sends items to users and collects 1-5 star ratings with persistent storage.",
            'features' => [
                'Rating scale' => '1 to 5 stars',
                'Multi-item' => 'Multiple survey items',
                'Media support' => 'Text, photo, video',
                'Data storage' => 'Persistent results',
            ],
            'usage_instructions' => "1. After creation, add rating items\n2. User sends /start\n3. Rate items 1-5\n4. 'end' to finish",
            'image_path' => 'images/bots/rating-bot.png',
        ]);

        // ==========================================
        // 9. PSYCHOLOGY TEST BOT
        // ==========================================
        $this->updateDetails('psychology-test', [
            'icon_emoji' => '🧠',
            'detailed_description' => "Create and take psychology tests with 5-choice questions, categorized scoring, weighted calculation, and results.",
            'features' => [
                '5-choice questions' => 'Standard format',
                'Categories' => 'Multiple categories',
                'Weighted scoring' => 'Custom weights',
                'Direction scoring' => 'Positive/negative',
                'Results' => 'Category-based results',
                'Admin panel' => 'Nova management',
            ],
            'usage_instructions' => "1. /start\n2. Select a test\n3. Answer questions\n4. View results",
            'image_path' => 'images/bots/psychology-test.png',
        ]);

        // ==========================================
        // 10. LIST BOT
        // ==========================================
        $this->updateDetails('list-bot', [
            'icon_emoji' => '📋',
            'detailed_description' => "Tree menu bot with inline buttons - unlimited depth menu with links to bots, channels, and websites.",
            'features' => [
                'Tree menu' => 'Unlimited depth',
                'Inline buttons' => 'Interactive navigation',
                'External links' => 'Bot/channel/website',
                'Easy format' => 'Dash-based hierarchy',
            ],
            'usage_instructions' => "1. After creation, enter menu\n2. Format: - Title, -- Sub: link\n3. User /start to see menu\n4. Click buttons to navigate",
            'image_path' => 'images/bots/list-bot.png',
        ]);

        // ==========================================
        // 11. CONTENT SUBMISSION
        // ==========================================
        $this->updateDetails('content-submission', [
            'icon_emoji' => '📝',
            'detailed_description' => "Content management pipeline: users submit text/images/videos → approval group moderates → auto-published to channel.",
            'features' => [
                'Content intake' => 'Text, photo, video',
                'Approval group' => 'Reply 1 to approve',
                'Auto-publish' => 'To configured channel',
                'Multi-approval' => '1 or 2 approvers',
                'Feedback' => 'Notify submitter',
            ],
            'usage_instructions' => "1. User sends content in private\n2. Forwarded to approval group\n3. Admin replies 1 to approve\n4. Published to channel\n5. Submitter notified",
            'image_path' => 'images/bots/content-submission.png',
        ]);

        // ==========================================
        // 12. BOOK LIBRARY
        // ==========================================
        $this->updateDetails('book-library', [
            'icon_emoji' => '📚',
            'detailed_description' => "AI-powered book coach - discover books, listen to audio summaries, upgrade plans, and track reading progress.",
            'features' => [
                'Book discovery' => 'AI recommendations',
                'Audio summaries' => 'Listen on-the-go',
                'Plan upgrades' => 'Premium features',
                'Progress tracking' => 'Reading history',
                'Categories' => 'Genre-based browsing',
            ],
            'usage_instructions' => "1. /start\n2. Search or browse books\n3. Select book for details\n4. Listen to audio summary\n5. Upgrade plan for more",
            'image_path' => 'images/bots/book-library.png',
        ]);

        // ==========================================
        // 13. BOOK LIBRARY READER
        // ==========================================
        $this->updateDetails('book-library-reader', [
            'icon_emoji' => '📖',
            'detailed_description' => "Dedicated reader bot for book audio/PDF delivery. Linked to main Book Library bot for content delivery.",
            'features' => [
                'Audio delivery' => 'Book audio files',
                'PDF delivery' => 'Book PDF files',
                'Linked' => 'Connects to library',
            ],
            'usage_instructions' => "Linked to Book Library bot. Users receive content through this reader bot after purchase.",
            'image_path' => 'images/bots/book-library-reader.png',
        ]);

        // ==========================================
        // 14. BOOK PIXEL
        // ==========================================
        $this->updateDetails('book-pixel', [
            'icon_emoji' => '📸',
            'detailed_description' => "Share book pages one by one - take photos of book pages and share them with followers sequentially.",
            'features' => [
                'Page sharing' => 'Photo per page',
                'Sequential' => 'Ordered display',
                'Approval' => 'Content moderation',
            ],
            'usage_instructions' => "1. Take photo of book page\n2. Send to bot\n3. Page shared with audience",
            'image_path' => 'images/bots/book-pixel.png',
        ]);

        // ==========================================
        // 15. POEM BOT
        // ==========================================
        $this->updateDetails('poem-bot', [
            'icon_emoji' => '🎭',
            'detailed_description' => "Poetry bot for submitting, editing, versioning, and liking poems. Collaborative poetry platform in messenger.",
            'features' => [
                'Submit poems' => 'Multi-format',
                'Version control' => 'Track changes',
                'Collaboration' => 'Co-author poems',
                'Likes' => 'User reactions',
                'Collections' => 'Organize poems',
            ],
            'usage_instructions' => "1. /start\n2. Submit your poem\n3. Edit and version\n4. Browse others' poems\n5. Like and comment",
            'image_path' => 'images/bots/poem-bot.png',
        ]);

        // ==========================================
        // 16. PERSONNEL REGISTRATION
        // ==========================================
        $this->updateDetails('personnel-registration', [
            'icon_emoji' => '👔',
            'detailed_description' => "Personnel registration bot - new staff can register their information through the bot with validation.",
            'features' => [
                'Registration' => 'Personal info collection',
                'Validation' => 'Data verification',
                'Storage' => 'Database persistence',
                'Admin notification' => 'Alert on new reg',
            ],
            'usage_instructions' => "1. /start\n2. Enter requested info\n3. Data validated and saved\n4. Admin notified",
            'image_path' => 'images/bots/personnel-registration.png',
        ]);

        // ==========================================
        // 17. PERSONNEL ADMIN
        // ==========================================
        $this->updateDetails('personnel-admin', [
            'icon_emoji' => '👨‍💼',
            'detailed_description' => "Admin panel for personnel management - view registrations, approve/reject, and manage staff records.",
            'features' => [
                'List view' => 'All registrations',
                'Approve/reject' => 'Manage applications',
                'Search' => 'Find personnel',
                'Status tracking' => 'Active/inactive',
            ],
            'usage_instructions' => "Admin-only bot. View and manage personnel registrations.",
            'image_path' => 'images/bots/personnel-admin.png',
        ]);

        // ==========================================
        // 18. MISSION BOT
        // ==========================================
        $this->updateDetails('mission-bot', [
            'icon_emoji' => '🎯',
            'detailed_description' => "Mission and task management for personnel with assignment, progress tracking, and completion reports.",
            'features' => [
                'Task assignment' => 'Assign to personnel',
                'Progress tracking' => 'Monitor status',
                'Reports' => 'Completion reports',
                'Approval' => 'Accept/reject work',
            ],
            'usage_instructions' => "1. Admin creates mission\n2. Assigned to personnel\n3. Personnel reports progress\n4. Admin approves/ rejects",
            'image_path' => 'images/bots/mission-bot.png',
        ]);

        // ==========================================
        // 19. MISSION MEDIA
        // ==========================================
        $this->updateDetails('mission-media', [
            'icon_emoji' => '🎬',
            'detailed_description' => "Educational media management for missions - upload, organize, and deliver training content.",
            'features' => [
                'Media upload' => 'Photos, videos, docs',
                'Organization' => 'By mission/category',
                'Delivery' => 'To assigned personnel',
            ],
            'usage_instructions' => "Upload and manage educational media for missions.",
            'image_path' => 'images/bots/mission-media.png',
        ]);

        // ==========================================
        // 20. TASK APPROVAL
        // ==========================================
        $this->updateDetails('task-approval', [
            'icon_emoji' => '✅',
            'detailed_description' => "Task approval workflow bot - admin reviews and approves/rejects tasks with notes.",
            'features' => [
                'Review tasks' => 'Pending approvals',
                'Approve/reject' => 'With notes',
                'Notifications' => 'Alert submitter',
            ],
            'usage_instructions' => "Admin reviews pending tasks and approves or rejects them.",
            'image_path' => 'images/bots/task-approval.png',
        ]);

        // ==========================================
        // 21. RSS FEED
        // ==========================================
        $this->updateDetails('rss-feed', [
            'icon_emoji' => '📰',
            'detailed_description' => "RSS feed auto-publisher - fetch, translate, and publish content from RSS feeds to messaging platforms.",
            'features' => [
                'Auto-fetch' => 'Scheduled RSS reading',
                'Translation' => 'Multi-language auto',
                'Publishing' => 'Telegram, Bale, Gap',
                'Queue system' => 'Efficient processing',
            ],
            'usage_instructions' => "Configured by admin. Automatically fetches and publishes RSS content.",
            'image_path' => 'images/bots/rss-feed.png',
        ]);

        // ==========================================
        // 22. BLOG BOT
        // ==========================================
        $this->updateDetails('blog-bot', [
            'icon_emoji' => '📝',
            'detailed_description' => "Connect your blog CMS to messaging platforms - automatically share new posts to Telegram/Bale channels.",
            'features' => [
                'Auto-publish' => 'New blog posts',
                'Multi-platform' => 'Telegram, Bale',
                'Formatting' => 'Rich text support',
            ],
            'image_path' => 'images/bots/blog-bot.png',
        ]);

        // ==========================================
        // 23. ADMIN DAILY CHANNEL
        // ==========================================
        $this->updateDetails('admin-daily-channel', [
            'icon_emoji' => '📢',
            'detailed_description' => "Daily auto-posting bot for religious content (verse, hadith, nahj, sharabe beheshti) to multiple channels.",
            'features' => [
                'Content types' => 'Verse, hadith, nahj, sharabe',
                'Mixed mode' => 'Random or sequential',
                'Post frequency' => '1, 2, or 4 times/day',
                'Multi-platform' => 'Bale, Telegram, Eitaa',
                'Content filter' => 'Admin-banned content',
            ],
            'usage_instructions' => "1. Select content type\n2. Choose posts per day (1/2/4)\n3. Configure channels\n4. Auto-posts daily",
            'image_path' => 'images/bots/admin-daily-channel.png',
        ]);

        // ==========================================
        // 24. ADMIN CHANNEL MEDIA QUEUE
        // ==========================================
        $this->updateDetails('admin-channel-media-queue', [
            'icon_emoji' => '📺',
            'detailed_description' => "Dynamic media queue for scheduled content delivery - create queues, add items, and publish on schedule.",
            'features' => [
                'Media queues' => 'Create & manage',
                'Scheduling' => 'Auto-publish',
                'Multi-platform' => 'Bale, Telegram, Eitaa',
                'Item types' => 'Text, photo, video',
            ],
            'usage_instructions' => "1. Create a media queue\n2. Add items (text/photo/video)\n3. Configure channels\n4. Auto-publishes in order",
            'image_path' => 'images/bots/admin-channel-media-queue.png',
        ]);

        // ==========================================
        // 25. SOCIAL BOT
        // ==========================================
        $this->updateDetails('social-bot', [
            'icon_emoji' => '🌐',
            'detailed_description' => "Cross-platform social media publisher - send content to Twitter, Facebook, LinkedIn, Instagram from one bot.",
            'features' => [
                'Multi-platform' => 'Twitter, FB, LinkedIn, IG',
                'Chrome extension' => 'Browser integration',
                'Queue' => 'Scheduled posting',
            ],
            'image_path' => 'images/bots/social-bot.png',
        ]);

        // ==========================================
        // 26. BLOG REGISTER BOT
        // ==========================================
        $this->updateDetails('blog-register', [
            'icon_emoji' => '📋',
            'detailed_description' => "Blog registration bot for managing blog authors and contributors.",
            'features' => [
                'Author registration' => 'New authors',
                'Management' => 'Author list',
            ],
            'image_path' => 'images/bots/blog-register.png',
        ]);

        // ==========================================
        // 27. RSS ADMIN BOT
        // ==========================================
        $this->updateDetails('rss-admin', [
            'icon_emoji' => '📡',
            'detailed_description' => "RSS feed management for admins - add, edit, and monitor RSS feeds.",
            'features' => [
                'Feed management' => 'Add/edit feeds',
                'Monitoring' => 'Feed health check',
                'Categories' => 'Organize feeds',
            ],
            'image_path' => 'images/bots/rss-admin.png',
        ]);

        // ==========================================
        // 28. AUDIO BOOK BOT
        // ==========================================
        $this->updateDetails('audio-book', [
            'icon_emoji' => '🎧',
            'detailed_description' => "Audio book management and delivery - upload, organize, and stream audio books to users.",
            'features' => [
                'Audio books' => 'Upload & manage',
                'Streaming' => 'In-chat playback',
                'Categories' => 'Genre organization',
            ],
            'image_path' => 'images/bots/audio-book.png',
        ]);

        // ==========================================
        // 29. SONG SARA BOT
        // ==========================================
        $this->updateDetails('song-sara', [
            'icon_emoji' => '🎵',
            'detailed_description' => "Music and playlist management with artist, country, genre, instrument, and mood-based categorization.",
            'features' => [
                'Music library' => 'Song management',
                'Playlists' => 'Create & share',
                'Categories' => 'Artist, genre, mood',
                'RSS publish' => 'Auto RSS feed',
            ],
            'image_path' => 'images/bots/song-sara.png',
        ]);

        // ==========================================
        // 30. GET CHAT ID BOT
        // ==========================================
        $this->updateDetails('get-chat-id', [
            'icon_emoji' => '🆔',
            'detailed_description' => "Get Chat ID bot - displays your chat ID, channel/group IDs, and detailed forwarded message information.",
            'features' => [
                'Chat ID' => 'Display your ID',
                'Forward info' => 'Channel/group details',
                'Sender info' => 'Message sender info',
                'Multi-messenger' => 'Bale, Telegram, Gap',
            ],
            'usage_instructions' => "1. /start - shows your chat ID\n2. Forward a message from channel/group\n3. Bot shows detailed info",
            'image_path' => 'images/bots/get-chat-id.png',
        ]);

        // ==========================================
        // 31. POEM BOT (duplicate)
        // ==========================================
        $this->updateDetails('poem-bot', [
            'icon_emoji' => '🎭',
            'detailed_description' => "Poetry bot for submitting, editing, versioning, and liking poems. Collaborative poetry platform in messenger.",
            'features' => [
                'Submit poems' => 'Multi-format support',
                'Version control' => 'Track poem changes',
                'Collaboration' => 'Co-author support',
                'Likes & feedback' => 'User engagement',
            ],
            'usage_instructions' => "1. /start\n2. Submit poem\n3. Edit versions\n4. Browse & like",
            'image_path' => 'images/bots/poem-bot.png',
        ]);

        // ==========================================
        // 32. SHARABE BEHESHTI MP3
        // ==========================================
        $this->updateDetails('sharabe-beheshti', [
            'icon_emoji' => '🍷',
            'detailed_description' => "Daily spiritual audio content - Nahj al-Balagha, Sahifeh Sajjadieh, Quran recitations, and supplications.",
            'features' => [
                'Daily audio' => 'New content daily',
                'Categories' => 'Nahj, Sahifeh, Quran, Dua',
                'Auto-post' => 'To channels/groups',
            ],
            'usage_instructions' => "Part of Admin Daily Channel system. Auto-posts scheduled content.",
            'image_path' => 'images/bots/sharabe-beheshti.png',
        ]);

        // ==========================================
        // 33. ADMIN BOTS
        // ==========================================
        $this->updateDetails('admin-bots', [
            'icon_emoji' => '🤖',
            'detailed_description' => "Personal bot management panel - register, create bots, manage subscriptions, and request Pro upgrades.",
            'features' => [
                'Bot management' => 'Your bots list',
                'Bot creation' => 'Create new bots',
                'Pro requests' => 'Upgrade system',
                'Profile' => 'Manage account',
            ],
            'image_path' => 'images/bots/admin-bots.png',
        ]);

        // ==========================================
        // 34. BOOK PIXEL APPROVAL
        // ==========================================
        $this->updateDetails('book-pixel-approval', [
            'icon_emoji' => '✅',
            'detailed_description' => "Book Pixel content moderation - admins approve or reject book page submissions before publishing.",
            'features' => [
                'Content review' => 'Pending approvals',
                'Approve/reject' => 'Moderation',
                'Publish' => 'Auto-publish approved',
            ],
            'image_path' => 'images/bots/book-pixel-approval.png',
        ]);

        Log::info('✅ All 34 bot details seeded successfully');
    }

    private function updateDetails(string $endpointId, array $data): void
    {
        $endpoint = WebhookEndpoint::where('endpoint_id', $endpointId)->first();

        if (!$endpoint) {
            Log::warning("Endpoint not found: {$endpointId}");
            return;
        }

        $endpoint->update($data);
        $this->command->info("✅ Details updated for: {$endpointId}");
    }
}
