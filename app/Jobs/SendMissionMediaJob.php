<?php

namespace App\Jobs;

use App\Builders\BotBuilder;
use App\Helpers\BotHelper;
use App\Interfaces\Repositories\ContentRepository;
use App\Models\BotUsers;
use App\Models\Content;
use App\Models\Mission;
use App\Models\Personnel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Telegram;

class SendMissionMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $missionId;
    protected int $personnelId;
    protected string $type;

    /**
     * Create a new job instance.
     */
    public function __construct(int $missionId, int $personnelId, string $type = 'telegram')
    {
        $this->missionId = $missionId;
        $this->personnelId = $personnelId;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(ContentRepository $contentRepository): void
    {
        Log::info('SendMissionMediaJob started', [
            'mission_id' => $this->missionId,
            'personnel_id' => $this->personnelId,
            'type' => $this->type
        ]);

        try {
            $mission = Mission::find($this->missionId);
            if (!$mission) {
                Log::error('Mission not found', ['mission_id' => $this->missionId]);
                return;
            }

            $personnel = Personnel::find($this->personnelId);
            if (!$personnel) {
                Log::error('Personnel not found', ['personnel_id' => $this->personnelId]);
                return;
            }

            // Get bot user chat_id by finding user with personnel_id in settings
            $botUser = BotUsers::where('origin', $this->type)
                ->whereJsonContains('settings->personnel_id', $this->personnelId)
                ->first();

            if (!$botUser) {
                Log::error('Bot user not found', [
                    'personnel_id' => $this->personnelId,
                    'type' => $this->type
                ]);
                return;
            }

            $chatId = $botUser->chat_id;

            // Get bot token
            $token = $this->getBotToken();
            if (!$token) {
                Log::error('Bot token not found', ['type' => $this->type]);
                return;
            }

            $messenger = new Telegram($token, $this->type);

            // Get contents for this mission
            $contents = $contentRepository->getByMission($this->missionId);

            if ($contents->isEmpty()) {
                Log::info('No contents found for mission', ['mission_id' => $this->missionId]);
                return;
            }

            // Send prompt if exists
            if ($mission->prompt) {
                $promptMessage = "📋 دستورالعمل ماموریت:\n\n" . $mission->prompt->content;
                BotHelper::sendMessageByChatId($messenger, $chatId, $promptMessage);
                Log::info('Prompt sent', ['mission_id' => $this->missionId, 'chat_id' => $chatId]);
            }

            // Send contents in order
            foreach ($contents as $content) {
                $this->sendContent($messenger, $chatId, $content);
                // Small delay between messages
                sleep(1);
            }

            Log::info('All media sent successfully', [
                'mission_id' => $this->missionId,
                'personnel_id' => $this->personnelId,
                'contents_count' => $contents->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in SendMissionMediaJob', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'mission_id' => $this->missionId,
                'personnel_id' => $this->personnelId
            ]);
            throw $e;
        }
    }

    /**
     * Send a content to user.
     */
    protected function sendContent(Telegram $messenger, int $chatId, Content $content): void
    {
        $botBuilder = new BotBuilder($messenger);
        $botBuilder->setChatId($chatId);
        $botBuilder->setTitle($content->title);
        $botBuilder->setCaption($content->description ?? '');

        try {
            switch ($content->content_type) {
                case 'image':
                    $botBuilder->setPhotoUrl($content->content_url);
                    $botBuilder->sendPhoto();
                    break;

                case 'video':
                    $this->sendVideo($messenger, $chatId, $content);
                    break;

                case 'audio':
                    $botBuilder->setAudioUrl($content->content_url);
                    $botBuilder->sendAudio();
                    break;

                case 'pdf':
                    $this->sendDocument($messenger, $chatId, $content);
                    break;

                case 'text':
                default:
                    $message = "📄 " . $content->title;
                    if ($content->description) {
                        $message .= "\n\n" . $content->description;
                    }
                    if ($content->content_url) {
                        $message .= "\n\n🔗 " . $content->content_url;
                    }
                    BotHelper::sendMessageByChatId($messenger, $chatId, $message);
                    break;
            }

            Log::info('Content sent', [
                'content_id' => $content->id,
                'content_type' => $content->content_type,
                'chat_id' => $chatId
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending content', [
                'content_id' => $content->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send video.
     */
    protected function sendVideo(Telegram $messenger, int $chatId, Content $content): void
    {
        $contentData = [
            'chat_id' => $chatId,
            'video' => $content->content_url,
            'caption' => $content->title . "\n\n" . ($content->description ?? ''),
            'parse_mode' => 'HTML'
        ];

        $messenger->sendVideo($contentData);
    }

    /**
     * Send document (PDF).
     */
    protected function sendDocument(Telegram $messenger, int $chatId, Content $content): void
    {
        $contentData = [
            'chat_id' => $chatId,
            'document' => $content->content_url,
            'caption' => $content->title . "\n\n" . ($content->description ?? ''),
            'parse_mode' => 'HTML'
        ];

        $messenger->sendDocument($contentData);
    }

    /**
     * Get bot token based on type.
     */
    protected function getBotToken(): ?string
    {
        if ($this->type === 'bale') {
            return env('MISSION_BOT_TOKEN_BALE');
        } elseif ($this->type === 'telegram') {
            return env('MISSION_BOT_TOKEN_TELEGRAM');
        }

        return null;
    }
}

