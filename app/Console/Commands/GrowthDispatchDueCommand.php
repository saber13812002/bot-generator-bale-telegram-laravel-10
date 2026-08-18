<?php

namespace App\Console\Commands;

use App\Http\Controllers\GrowthCompanionController;
use App\Interfaces\Services\GrowthCompanionService;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\BotUserState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrowthDispatchDueCommand extends Command
{
    protected $signature = 'growth:dispatch-due {--limit=50 : Max due schedules per run}';

    protected $description = 'Send due Growth Companion questions to users';

    public function __construct(
        private GrowthCompanionService $service,
        private GrowthMessengerFactory $messengerFactory
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $schedules = $this->service->dueSchedules($limit);
        $sent = 0;
        $skipped = 0;

        foreach ($schedules as $schedule) {
            try {
                $question = $schedule->question;
                $program = $question?->program;
                $profile = $program?->profile;
                if (!$question || !$program || !$profile) {
                    $skipped++;
                    continue;
                }

                $reason = $this->service->dispatchSkipReason($schedule, $profile);
                if ($reason === 'quiet') {
                    $skipped++;
                    continue;
                }
                if ($reason !== null) {
                    $schedule->next_due_at = $this->service->computeNextDueAt($profile, $question->frequency);
                    $schedule->save();
                    Log::info('[GrowthCompanion] dispatch_skipped_'.$reason, [
                        'bot_id' => $profile->bot_id,
                        'schedule_id' => $schedule->id,
                    ]);
                    $skipped++;
                    continue;
                }

                $bot = Bot::find($profile->bot_id);
                $botUser = BotUsers::find($profile->bot_user_id);
                if (!$bot || !$botUser) {
                    $skipped++;
                    continue;
                }

                $origin = $botUser->origin ?: 'telegram';
                $token = $origin === 'bale' ? $bot->bale_bot_token : $bot->telegram_bot_token;
                if (!$token) {
                    $skipped++;
                    continue;
                }

                if ($bot->language_code) {
                    app()->setLocale($bot->language_code);
                }

                $variant = $this->service->pickVariant($question, $botUser->id, $bot->language_code);
                if (!$variant) {
                    $skipped++;
                    continue;
                }

                $messenger = $this->messengerFactory->make($token, $origin);
                $messenger->send(
                    (string) $botUser->chat_id,
                    trans('growth_companion.today')."\n\n".$variant->body,
                    [
                        [
                            ['text' => trans('growth_companion.btn_later'), 'callback_data' => 'gc:later'],
                            ['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set'],
                        ],
                    ]
                );

                $this->service->markSent($schedule, $profile);

                BotUserState::where('bot_user_id', $botUser->id)
                    ->where('bot_mother_id', (int) ($bot->bot_mother_id ?? $bot->id))
                    ->delete();
                BotUserState::create([
                    'bot_user_id' => $botUser->id,
                    'bot_mother_id' => (int) ($bot->bot_mother_id ?? $bot->id),
                    'state' => GrowthCompanionController::STATE_AWAITING_ANSWER,
                    'data' => [
                        'question_id' => $question->id,
                        'variant_id' => $variant->id,
                    ],
                    'expires_at' => now()->addHours(18),
                ]);

                $sent++;
                Log::info('[GrowthCompanion] dispatch_sent', [
                    'bot_id' => $profile->bot_id,
                    'schedule_id' => $schedule->id,
                ]);
            } catch (Throwable $e) {
                Log::error('[GrowthCompanion] Dispatch failed', [
                    'schedule_id' => $schedule->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Growth dispatch: sent={$sent} skipped={$skipped}");

        return 0;
    }
}
