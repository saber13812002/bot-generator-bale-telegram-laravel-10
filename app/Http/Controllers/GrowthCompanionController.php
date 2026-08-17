<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\GrowthCompanionService;
use App\Interfaces\Services\GrowthMessenger;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\BotUserState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrowthCompanionController extends Controller
{
    public const ENDPOINT_ID = 'webhook-growth-companion';

    public const STATE_FOCUS = 'gc_onboarding_focus';
    public const STATE_INTENSITY = 'gc_onboarding_intensity';
    public const STATE_TIME = 'gc_onboarding_time';
    public const STATE_CUSTOM_FOCUS = 'gc_onboarding_custom_focus';
    public const STATE_AWAITING_ANSWER = 'gc_awaiting_answer';
    public const STATE_CUSTOM_QUESTION = 'gc_awaiting_custom_question';

    public const FOCUSES = [
        'health',
        'family',
        'work',
        'spirituality',
        'study',
        'self',
        'sport',
        'relations',
        'custom',
    ];

    public function __construct(
        private GrowthCompanionService $service,
        private GrowthMessengerFactory $messengerFactory
    ) {
    }

    public function webhook(Request $request): JsonResponse
    {
        Log::info('[GrowthCompanion] Webhook received', [
            'origin' => $request->input('origin'),
            'has_token' => $request->has('token'),
            'has_bot_id' => $request->has('bot_id'),
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $resolved = $this->resolveBotAndToken($request, $type);
            if (!$resolved) {
                Log::warning('[GrowthCompanion] Bot or token missing');

                return response()->json(['status' => 'error'], 200);
            }

            [$botItem, $token] = $resolved;
            if ($botItem->endpoint_id && $botItem->endpoint_id !== self::ENDPOINT_ID) {
                Log::warning('[GrowthCompanion] Wrong endpoint', [
                    'bot_id' => $botItem->id,
                    'endpoint_id' => $botItem->endpoint_id,
                ]);

                return response()->json(['status' => 'error'], 200);
            }

            if ($botItem->language_code) {
                app()->setLocale($botItem->language_code);
            }

            $messenger = $this->messengerFactory->make($token, $type);
            $update = $this->extractUpdate($request);

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($messenger, $update['callback_query'], $botItem, $type);

                return response()->json(['status' => 'ok'], 200);
            }

            $message = $update['message'] ?? $update['edited_message'] ?? null;
            if (!is_array($message)) {
                return response()->json(['status' => 'ok'], 200);
            }

            $chatId = (string) ($message['chat']['id'] ?? $message['from']['id'] ?? '');
            if ($chatId === '') {
                return response()->json(['status' => 'ok'], 200);
            }

            $this->handlePrivateMessage($messenger, $message, $botItem, $type, $chatId);

            return response()->json(['status' => 'ok'], 200);
        } catch (Throwable $e) {
            Log::error('[GrowthCompanion] Webhook error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function handlePrivateMessage(
        GrowthMessenger $messenger,
        array $message,
        Bot $botItem,
        string $type,
        string $chatId
    ): void {
        $text = isset($message['text']) ? trim((string) $message['text']) : '';
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        $state = $this->getState($botItem, $chatId, $type);

        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->handleStart($messenger, $botItem, $type, $chatId, $botUser);

            return;
        }

        if ($text === '/privacy') {
            $this->sendPrivacy($messenger, $chatId);

            return;
        }

        if ($text === '/settings') {
            $this->sendSettings($messenger, $botItem, $chatId, $botUser);

            return;
        }

        if ($state && $state->state === self::STATE_CUSTOM_FOCUS && $text !== '') {
            $this->setState($botItem, $chatId, $type, self::STATE_INTENSITY, [
                'focus' => 'custom',
                'custom_focus' => $text,
            ]);
            $this->sendIntensity($messenger, $chatId);

            return;
        }

        if ($state && $state->state === self::STATE_CUSTOM_QUESTION && $text !== '') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $question = $this->service->addCustomQuestion($profile, $text);
            $variant = $question->variants->first();
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_ANSWER, [
                'question_id' => $question->id,
                'variant_id' => $variant?->id,
            ]);
            $messenger->send($chatId, trans('growth_companion.custom_question_saved'));
            $this->sendQuestion($messenger, $chatId, $variant?->body ?? $text);

            return;
        }

        if ($state && $state->state === self::STATE_AWAITING_ANSWER && $text !== '' && !str_starts_with($text, '/')) {
            $questionId = (int) $state->getData('question_id');
            $variantId = $state->getData('variant_id') ? (int) $state->getData('variant_id') : null;
            $question = \App\Models\GrowthQuestion::find($questionId);
            if ($question) {
                $this->service->recordResponse($question, $botUser, $botItem->id, $text, $variantId);
            }
            $this->clearState($botItem, $chatId, $type);
            $messenger->send($chatId, trans('growth_companion.ack'), $this->simpleAfterAnswerKeyboard());

            return;
        }

        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        if ($profile->onboarding_completed_at) {
            $messenger->send($chatId, trans('growth_companion.use_buttons'), $this->simpleHomeKeyboard());

            return;
        }

        $this->handleStart($messenger, $botItem, $type, $chatId, $botUser);
    }

    private function handleCallbackQuery(
        GrowthMessenger $messenger,
        array $callbackQuery,
        Bot $botItem,
        string $type
    ): void {
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? $callbackQuery['from']['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $callbackId = $callbackQuery['id'] ?? null;

        if ($chatId === '' || !str_starts_with($data, 'gc:')) {
            return;
        }

        $messenger->answerCallback($callbackId);
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        $payload = substr($data, 3);

        if (str_starts_with($payload, 'f:')) {
            $focus = substr($payload, 2);
            if (!in_array($focus, self::FOCUSES, true)) {
                return;
            }
            if ($focus === 'custom') {
                $this->setState($botItem, $chatId, $type, self::STATE_CUSTOM_FOCUS, ['focus' => 'custom']);
                $messenger->send($chatId, trans('growth_companion.ask_custom_focus'));

                return;
            }
            $this->setState($botItem, $chatId, $type, self::STATE_INTENSITY, ['focus' => $focus]);
            $this->sendIntensity($messenger, $chatId);

            return;
        }

        if (str_starts_with($payload, 'i:')) {
            $intensity = match (substr($payload, 2)) {
                'min' => 'minimal',
                'act' => 'active',
                default => 'balanced',
            };
            $state = $this->getState($botItem, $chatId, $type);
            $this->setState($botItem, $chatId, $type, self::STATE_TIME, [
                'focus' => $state?->getData('focus', 'self'),
                'custom_focus' => $state?->getData('custom_focus'),
                'intensity' => $intensity,
            ]);
            $this->sendTime($messenger, $chatId);

            return;
        }

        if (str_starts_with($payload, 't:')) {
            $this->finishOnboarding($messenger, $botItem, $type, $chatId, $botUser, substr($payload, 2));

            return;
        }

        if ($payload === 'later') {
            $this->clearState($botItem, $chatId, $type);
            $messenger->send($chatId, trans('growth_companion.later_ack'), $this->simpleHomeKeyboard());

            return;
        }

        if ($payload === 'set') {
            $this->sendSettings($messenger, $botItem, $chatId, $botUser);

            return;
        }

        if ($payload === 'priv') {
            $this->sendPrivacy($messenger, $chatId);

            return;
        }

        if ($payload === 'p') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->pauseActiveQuestion($profile);
            $messenger->send($chatId, trans('growth_companion.paused'), $this->simpleHomeKeyboard());

            return;
        }

        if ($payload === 'u') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->unpauseActiveQuestion($profile);
            $messenger->send($chatId, trans('growth_companion.unpaused'), $this->simpleHomeKeyboard());

            return;
        }

        if ($payload === 'freq:d' || $payload === 'freq:w') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->setFrequency($profile, $payload === 'freq:d' ? 'daily' : 'weekly');
            $messenger->send($chatId, trans('growth_companion.frequency_updated'), $this->simpleHomeKeyboard());

            return;
        }

        if ($payload === 'cq') {
            $this->setState($botItem, $chatId, $type, self::STATE_CUSTOM_QUESTION);
            $messenger->send($chatId, trans('growth_companion.ask_custom_question'));

            return;
        }

        if ($payload === 'delp') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->deleteProgram($profile);
            $this->clearState($botItem, $chatId, $type);
            $messenger->send($chatId, trans('growth_companion.program_deleted'), $this->restartKeyboard());

            return;
        }

        if ($payload === 'deld') {
            $this->service->deleteAllGrowthData($botUser, $botItem->id);
            $this->clearState($botItem, $chatId, $type);
            $messenger->send($chatId, trans('growth_companion.data_deleted'), $this->restartKeyboard());

            return;
        }

        if ($payload === 'ask') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            if (!$this->service->activeQuestion($profile, true)) {
                $this->handleStart($messenger, $botItem, $type, $chatId, $botUser);

                return;
            }
            $this->sendCurrentQuestion($messenger, $botItem, $type, $chatId, $botUser);
        }
    }

    private function handleStart(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        if ($profile->onboarding_completed_at && $this->service->activeQuestion($profile, true)) {
            $this->sendCurrentQuestion($messenger, $botItem, $type, $chatId, $botUser);

            return;
        }

        $this->clearState($botItem, $chatId, $type);
        $this->setState($botItem, $chatId, $type, self::STATE_FOCUS);
        $messenger->send($chatId, trans('growth_companion.welcome')."\n\n".trans('growth_companion.ask_focus'), $this->focusKeyboard());
    }

    private function finishOnboarding(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser,
        string $timeCode
    ): void {
        $state = $this->getState($botItem, $chatId, $type);
        $focus = (string) ($state?->getData('focus') ?? 'self');
        $intensity = (string) ($state?->getData('intensity') ?? 'balanced');
        $customFocus = $state?->getData('custom_focus');
        $notifyTime = match ($timeCode) {
            '09' => '09:00:00',
            '12' => '12:00:00',
            default => '21:00:00',
        };

        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $program = $this->service->completeOnboarding($profile, $focus, $intensity, $notifyTime, $customFocus);
        $question = $program->questions->first();
        $variant = $question
            ? $this->service->pickVariant($question, $botUser->id, $botItem->language_code)
            : null;

        if ($question && $variant) {
            $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_ANSWER, [
                'question_id' => $question->id,
                'variant_id' => $variant->id,
            ]);
            $this->sendQuestion($messenger, $chatId, $variant->body);
        } else {
            $this->clearState($botItem, $chatId, $type);
            $messenger->send($chatId, trans('growth_companion.error'), $this->simpleHomeKeyboard());
        }
    }

    private function sendCurrentQuestion(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $question = $this->service->activeQuestion($profile);
        if (!$question) {
            $messenger->send($chatId, trans('growth_companion.no_active_question'), $this->simpleHomeKeyboard());

            return;
        }

        $variant = $this->service->pickVariant($question, $botUser->id, $botItem->language_code);
        if (!$variant) {
            $messenger->send($chatId, trans('growth_companion.error'), $this->simpleHomeKeyboard());

            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_ANSWER, [
            'question_id' => $question->id,
            'variant_id' => $variant->id,
        ]);
        $this->sendQuestion($messenger, $chatId, $variant->body);
    }

    private function sendQuestion(GrowthMessenger $messenger, string $chatId, string $body): void
    {
        $messenger->send($chatId, trans('growth_companion.today')."\n\n".$body, [
            [
                ['text' => trans('growth_companion.btn_later'), 'callback_data' => 'gc:later'],
                ['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set'],
            ],
        ]);
    }

    private function sendIntensity(GrowthMessenger $messenger, string $chatId): void
    {
        $messenger->send($chatId, trans('growth_companion.ask_intensity'), [
            [['text' => trans('growth_companion.intensity.minimal'), 'callback_data' => 'gc:i:min']],
            [['text' => trans('growth_companion.intensity.balanced'), 'callback_data' => 'gc:i:bal']],
            [['text' => trans('growth_companion.intensity.active'), 'callback_data' => 'gc:i:act']],
        ]);
    }

    private function sendTime(GrowthMessenger $messenger, string $chatId): void
    {
        $messenger->send($chatId, trans('growth_companion.ask_time'), [
            [
                ['text' => trans('growth_companion.time.morning'), 'callback_data' => 'gc:t:09'],
                ['text' => trans('growth_companion.time.noon'), 'callback_data' => 'gc:t:12'],
            ],
            [
                ['text' => trans('growth_companion.time.evening'), 'callback_data' => 'gc:t:21'],
                ['text' => trans('growth_companion.time.skip'), 'callback_data' => 'gc:t:skip'],
            ],
        ]);
    }

    private function sendSettings(GrowthMessenger $messenger, Bot $botItem, string $chatId, BotUsers $botUser): void
    {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $question = $this->service->activeQuestion($profile, true);
        $paused = $question?->paused_at !== null;

        $rows = [
            [[
                'text' => $paused ? trans('growth_companion.btn_unpause') : trans('growth_companion.btn_pause'),
                'callback_data' => $paused ? 'gc:u' : 'gc:p',
            ]],
            [
                ['text' => trans('growth_companion.btn_daily'), 'callback_data' => 'gc:freq:d'],
                ['text' => trans('growth_companion.btn_weekly'), 'callback_data' => 'gc:freq:w'],
            ],
            [['text' => trans('growth_companion.btn_custom_question'), 'callback_data' => 'gc:cq']],
            [['text' => trans('growth_companion.btn_privacy'), 'callback_data' => 'gc:priv']],
            [['text' => trans('growth_companion.btn_ask'), 'callback_data' => 'gc:ask']],
        ];

        $messenger->send($chatId, trans('growth_companion.settings_title'), $rows);
    }

    private function sendPrivacy(GrowthMessenger $messenger, string $chatId): void
    {
        $messenger->send($chatId, trans('growth_companion.privacy_intro'), [
            [['text' => trans('growth_companion.btn_pause'), 'callback_data' => 'gc:p']],
            [['text' => trans('growth_companion.btn_delete_program'), 'callback_data' => 'gc:delp']],
            [['text' => trans('growth_companion.btn_delete_data'), 'callback_data' => 'gc:deld']],
        ]);
    }

    private function focusKeyboard(): array
    {
        $rows = [];
        $row = [];
        foreach (self::FOCUSES as $focus) {
            $row[] = [
                'text' => trans('growth_companion.focus.'.$focus),
                'callback_data' => 'gc:f:'.$focus,
            ];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function simpleHomeKeyboard(): array
    {
        return [
            [
                ['text' => trans('growth_companion.btn_ask'), 'callback_data' => 'gc:ask'],
                ['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set'],
            ],
        ];
    }

    private function simpleAfterAnswerKeyboard(): array
    {
        return [
            [['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set']],
        ];
    }

    private function restartKeyboard(): array
    {
        return [
            [['text' => trans('growth_companion.btn_restart'), 'callback_data' => 'gc:ask']],
        ];
    }

    private function resolveBotAndToken(Request $request, string $type): ?array
    {
        $token = $request->input('token');
        $botId = $request->input('bot_id');

        if ($token) {
            $botItem = $type === 'bale'
                ? Bot::where('bale_bot_token', $token)->first()
                : Bot::where('telegram_bot_token', $token)->first();
            if ($botItem) {
                return [$botItem, $token];
            }
        }

        if ($botId) {
            $botItem = Bot::find($botId);
            if (!$botItem) {
                return null;
            }
            $dbToken = $type === 'bale' ? $botItem->bale_bot_token : $botItem->telegram_bot_token;
            if (!$dbToken) {
                return null;
            }

            return [$botItem, $dbToken];
        }

        return null;
    }

    private function extractUpdate(Request $request): array
    {
        $candidates = [];
        $raw = $request->getContent();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $candidates[] = $decoded;
            }
        }
        $json = $request->json()->all();
        if (is_array($json) && $json !== []) {
            $candidates[] = $json;
        }
        $all = $request->all();
        if (is_array($all) && $all !== []) {
            $candidates[] = $all;
        }

        foreach ($candidates as $data) {
            unset($data['origin'], $data['token'], $data['bot_id'], $data['bot_mother_id'], $data['language']);
            if (isset($data['message']) || isset($data['callback_query']) || isset($data['edited_message'])) {
                return $data;
            }
        }

        $fallback = $candidates[0] ?? [];
        unset($fallback['origin'], $fallback['token'], $fallback['bot_id'], $fallback['bot_mother_id'], $fallback['language']);

        return is_array($fallback) ? $fallback : [];
    }

    private function resolveBotUser(Bot $botItem, string $chatId, string $type): BotUsers
    {
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botItem->id)
            ->first();

        if (!$botUser) {
            $botUser = BotUsers::create([
                'chat_id' => $chatId,
                'bot_id' => $botItem->id,
                'origin' => $type,
                'status' => 'active',
            ]);
        }

        return $botUser;
    }

    private function motherId(Bot $botItem): int
    {
        return (int) ($botItem->bot_mother_id ?? $botItem->id);
    }

    private function getState(Bot $botItem, string $chatId, string $type): ?BotUserState
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);

        return BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $this->motherId($botItem))
            ->active()
            ->latest('id')
            ->first();
    }

    private function setState(Bot $botItem, string $chatId, string $type, string $state, array $data = []): void
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        $motherId = $this->motherId($botItem);

        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $motherId)
            ->delete();

        BotUserState::create([
            'bot_user_id' => $botUser->id,
            'bot_mother_id' => $motherId,
            'state' => $state,
            'data' => $data,
            'expires_at' => now()->addHours(6),
        ]);
    }

    private function clearState(Bot $botItem, string $chatId, string $type): void
    {
        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        BotUserState::where('bot_user_id', $botUser->id)
            ->where('bot_mother_id', $this->motherId($botItem))
            ->delete();
    }
}
