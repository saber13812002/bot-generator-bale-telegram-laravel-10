<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Models\ChannelPosterDestination;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Telegram;

/**
 * Artisan command: app:daily-blog-reminder
 * Sends a reminder to the admin(s) to publish a blog post and mention a random channel.
 */
class DailyBlogReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:daily-blog-reminder';

    /**
     * The console command description.
     */
    protected $description = 'Send daily reminder to admin to post a blog mentioning a random channel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Pick a random channel destination. If none exist, just log and exit.
        $channel = ChannelPosterDestination::inRandomOrder()->first();
        if (! $channel) {
            Log::warning('DailyBlogReminder: No ChannelPosterDestination records found.');
            return 0;
        }

        $message = "📝 لطفاً امروز یک مطلب جدید در وبلاگ منتشر کنید و در کانال «{$channel->channel_title}» ({$channel->channel_chat_id}) ذکر کنید.";

        // Determine which messengers to use (env defaults to both)
        $messengers = explode(',', env('DAILY_BLOG_REMINDER_MESSENGERS', 'both'));
        $messengers = array_map('trim', $messengers);

        foreach ($messengers as $messenger) {
            try {
                if ($messenger === 'both' || $messenger === 'bale') {
                    $token = env('BOT_MOTHER_TOKEN_BALE');
                    if ($token) {
                        $bale = new Telegram($token, 'bale');
                        BotHelper::sendMessage($bale, $message);
                    }
                }
                if ($messenger === 'both' || $messenger === 'telegram') {
                    $token = env('BOT_MOTHER_TOKEN_TELEGRAM');
                    if ($token) {
                        $tg = new Telegram($token, 'telegram');
                        BotHelper::sendMessage($tg, $message);
                    }
                }
            } catch (\Exception $e) {
                Log::error('DailyBlogReminder: failed to send via ' . $messenger, ['error' => $e->getMessage()]);
            }
        }

        Log::info('DailyBlogReminder sent', [
            'channel_id' => $channel->id,
            'message'    => $message,
        ]);

        return 0;
    }
}
