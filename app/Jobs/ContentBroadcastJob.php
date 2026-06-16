<?php

namespace App\Jobs;

use App\Helpers\BotHelper;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\ContentBroadcastJob as ContentBroadcastJobModel;
use App\Models\ContentUserProgress;
use App\Models\LibraryUserSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram;

class ContentBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $broadcastJobId
    ) {}

    public function handle(): void
    {
        $job = ContentBroadcastJobModel::find($this->broadcastJobId);
        if (!$job || $job->status !== 'pending') {
            return;
        }

        $botModel = Bot::find($job->bot_id);
        if (!$botModel) {
            $job->update(['status' => 'failed']);
            return;
        }

        $origin = $botModel->type ?? 'bale';
        $token = $origin === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
        if (!$token) {
            $job->update(['status' => 'failed']);
            return;
        }

        $bot = $origin === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
        $recipients = $this->resolveRecipients($job, $origin);

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $user) {
            try {
                if ($job->file_id && $job->file_type === 'audio') {
                    $bot->sendAudio(['chat_id' => $user->chat_id, 'audio' => $job->file_id, 'caption' => $job->message_text]);
                } elseif ($job->message_text) {
                    BotHelper::sendMessageByChatId($bot, $user->chat_id, $job->message_text);
                }
                $sent++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        $job->update([
            'status' => 'completed',
            'sent_count' => $sent,
            'failed_count' => $failed,
        ]);

        Log::info('ContentBroadcastJob completed', ['id' => $job->id, 'sent' => $sent, 'failed' => $failed]);
    }

    private function resolveRecipients(ContentBroadcastJobModel $job, string $origin): \Illuminate\Support\Collection
    {
        $query = BotUsers::where('status', 'active')->where('origin', $origin);

        return match ($job->target_filter) {
            'free' => $query->get()->filter(fn ($u) => !$this->isPaidUser($u, $job->bot_id)),
            'paid' => $query->get()->filter(fn ($u) => $this->isPaidUser($u, $job->bot_id)),
            'active' => $query->whereIn('id', ContentUserProgress::where('bot_id', $job->bot_id)
                ->where('last_position', '>', 0)->pluck('bot_user_id'))->get(),
            'category' => $job->target_category_id
                ? ContentUserProgress::where('category_id', $job->target_category_id)->where('last_position', '>', 0)
                    ->with('botUser')->get()->pluck('botUser')->filter()
                : collect(),
            default => $query->whereIn('id', LibraryUserSubscription::where('bot_id', $job->bot_id)->pluck('bot_user_id'))->get(),
        };
    }

    private function isPaidUser(BotUsers $user, int $botId): bool
    {
        $sub = LibraryUserSubscription::where('bot_user_id', $user->id)->where('bot_id', $botId)->first();
        return $sub && $sub->plan !== 'free';
    }
}
