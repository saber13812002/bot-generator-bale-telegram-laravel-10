<?php

namespace App\Http\Controllers;

use App\Interfaces\Services\GrowthCompanionService;
use App\Interfaces\Services\GrowthMessenger;
use App\Interfaces\Services\GrowthMessengerFactory;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Models\BotUserState;
use App\Models\GrowthProfile;
use App\Models\GrowthProfileTopic;
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
    public const STATE_ADD_CUSTOM = 'gc_add_custom_topic';
    public const STATE_WEEKLY_REVIEW = 'gc_weekly_review';
    public const STATE_EVENING = 'gc_evening_wrap';

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
            $this->sendMore($messenger, $botItem, $chatId, $botUser);

            return;
        }

        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        if ($profile->onboarding_completed_at && $text !== '') {
            $nav = $this->navAction($text, $profile);
            if ($nav) {
                $this->handleNav($messenger, $botItem, $type, $chatId, $botUser, $nav);

                return;
            }
        }

        if ($state && $state->state === self::STATE_CUSTOM_FOCUS && $text !== '') {
            $this->setState($botItem, $chatId, $type, self::STATE_INTENSITY, [
                'focus' => 'custom',
                'custom_focus' => $text,
            ]);
            $this->sendIntensity($messenger, $chatId);

            return;
        }

        if ($state && $state->state === self::STATE_ADD_CUSTOM && $text !== '') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->addCustomTopic($profile, $text);
            $this->clearState($botItem, $chatId, $type);
            $this->sendTopics($messenger, $profile, $chatId, trans('growth_companion.topic_added'));

            return;
        }

        if ($state && $state->state === self::STATE_EVENING && $text !== '' && !str_starts_with($text, '/')) {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->upsertCheckin($profile, ['evening_note' => $text]);
            $this->clearState($botItem, $chatId, $type);
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.evening_saved'));

            return;
        }

        if ($state && $state->state === self::STATE_WEEKLY_REVIEW && $text !== '') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $review = $this->service->saveWeeklyReview($profile, $text);
            $this->clearState($botItem, $chatId, $type);
            $this->sendReviewSummary($messenger, $review, $chatId);

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
            $this->sendQuestion($messenger, $chatId, $variant?->body ?? $text, trans('growth_companion.focus.custom'));

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
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->afterQuestionAnswered($messenger, $botItem, $type, $chatId, $botUser, $profile);

            return;
        }

        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        if ($profile->onboarding_completed_at) {
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.use_buttons'));

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
        $messageId = $callbackQuery['message']['message_id'] ?? null;

        if ($chatId === '' || !str_starts_with($data, 'gc:')) {
            return;
        }

        $botUser = $this->resolveBotUser($botItem, $chatId, $type);
        $payload = substr($data, 3);

        if (str_starts_with($payload, 'b:')) {
            $this->handleBoardTap($messenger, $botItem, $type, $chatId, $botUser, substr($payload, 2), $callbackId, $messageId);

            return;
        }

        $messenger->answerCallback($callbackId);

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

        if (str_starts_with($payload, 'i:') || str_starts_with($payload, 'int:')) {
            $code = str_starts_with($payload, 'int:') ? substr($payload, 4) : substr($payload, 2);
            $intensity = match ($code) {
                'min' => 'minimal',
                'act' => 'active',
                default => 'balanced',
            };
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            if ($profile->onboarding_completed_at) {
                $this->service->setIntensity($profile, $intensity);
                $this->sendSettings($messenger, $botItem, $chatId, $botUser, trans('growth_companion.intensity_updated'));

                return;
            }
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

        if ($payload === 'home' || $payload === 'ask') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            if (!$profile->onboarding_completed_at) {
                $this->handleStart($messenger, $botItem, $type, $chatId, $botUser);

                return;
            }
            $this->sendHome($messenger, $profile, $chatId);

            return;
        }

        if ($payload === 'q') {
            $this->openNextQuestion($messenger, $botItem, $type, $chatId, $botUser);

            return;
        }

        if ($payload === 'more') {
            $this->sendMore($messenger, $botItem, $chatId, $botUser);

            return;
        }

        if ($payload === 'hist') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendHistory($messenger, $profile, $chatId);

            return;
        }

        if ($payload === 'topics') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendTopics($messenger, $profile, $chatId);

            return;
        }

        if ($payload === 'in') {
            $this->startCheckin($messenger, $chatId);

            return;
        }

        if (str_starts_with($payload, 'in:')) {
            $this->handleCheckinChoice($messenger, $botItem, $chatId, $botUser, substr($payload, 3));

            return;
        }

        if ($payload === 'eve') {
            $this->setState($botItem, $chatId, $type, self::STATE_EVENING);
            $messenger->send($chatId, trans('growth_companion.ask_evening'));

            return;
        }

        if (str_starts_with($payload, 'ft:')) {
            $id = (int) substr($payload, 3);
            $this->sendFullText($messenger, $botUser, $botItem, $chatId, $id);

            return;
        }

        if ($payload === 'later') {
            $this->clearState($botItem, $chatId, $type);
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.later_ack'));

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
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.paused'));

            return;
        }

        if ($payload === 'u') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->unpauseActiveQuestion($profile);
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.unpaused'));

            return;
        }

        if ($payload === 'freq:d' || $payload === 'freq:w') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->setFrequency($profile, $payload === 'freq:d' ? 'daily' : 'weekly');
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.frequency_updated'));

            return;
        }

        if (str_starts_with($payload, 'c:')) {
            $slug = substr($payload, 2);
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $topic = $this->topicBySlug($profile, $slug);
            if ($topic) {
                $next = $topic->cadence === 'weekly' ? 'daily' : 'weekly';
                $this->service->setTopicCadence($profile, $slug, $next);
            }
            $this->sendCadenceMenu($messenger, $profile, $chatId, trans('growth_companion.cadence_updated'));

            return;
        }

        if ($payload === 'cad') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendCadenceMenu($messenger, $profile, $chatId);

            return;
        }

        if ($payload === 'wdm') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendWeekdayTopicMenu($messenger, $profile, $chatId);

            return;
        }

        if (str_starts_with($payload, 'w:')) {
            $slug = substr($payload, 2);
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendWeekdayPicker($messenger, $profile, $chatId, $slug);

            return;
        }

        if (str_starts_with($payload, 'k:')) {
            $rest = substr($payload, 2);
            $parts = explode(':', $rest);
            $slug = $parts[0] ?? '';
            $day = isset($parts[1]) ? (int) $parts[1] : -1;
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->toggleTopicWeekday($profile, $slug, $day);
            $this->sendWeekdayPicker($messenger, $profile, $chatId, $slug, trans('growth_companion.weekday_updated'));

            return;
        }

        if ($payload === 'add') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendAddTopicMenu($messenger, $profile, $chatId);

            return;
        }

        if (str_starts_with($payload, 'a:')) {
            $slug = substr($payload, 2);
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            if ($slug === 'custom') {
                $this->setState($botItem, $chatId, $type, self::STATE_ADD_CUSTOM);
                $messenger->send($chatId, trans('growth_companion.ask_custom_topic'));

                return;
            }
            $this->service->enableTopic($profile, $slug);
            $this->sendTopics($messenger, $profile, $chatId, trans('growth_companion.topic_added'));

            return;
        }

        if ($payload === 'del') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->sendRemoveTopicMenu($messenger, $profile, $chatId);

            return;
        }

        if (str_starts_with($payload, 'x:')) {
            $slug = substr($payload, 2);
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->disableTopic($profile, $slug);
            $this->sendTopics($messenger, $profile, $chatId, trans('growth_companion.topic_removed'));

            return;
        }

        if ($payload === 'cq') {
            $this->setState($botItem, $chatId, $type, self::STATE_CUSTOM_QUESTION);
            $messenger->send($chatId, trans('growth_companion.ask_custom_question'), [
                [['text' => trans('growth_companion.btn_later'), 'callback_data' => 'gc:later']],
            ]);

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

        if ($payload === 'rev') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $messenger->send(
                $chatId,
                $this->service->weeklyReviewText($profile),
                [
                    [['text' => trans('growth_companion.btn_save_review'), 'callback_data' => 'gc:revw']],
                    [['text' => trans('growth_companion.nav.today'), 'callback_data' => 'gc:home']],
                ],
                null,
                'HTML'
            );

            return;
        }

        if ($payload === 'revw') {
            $this->setState($botItem, $chatId, $type, self::STATE_WEEKLY_REVIEW);
            $messenger->send($chatId, trans('growth_companion.weekly_review_ask'));

            return;
        }

        if ($payload === 'exp') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $json = json_encode($this->service->exportData($profile), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (!is_string($json)) {
                $json = '{}';
            }
            if (strlen($json) > 3500) {
                $json = substr($json, 0, 3500).'…';
            }
            $messenger->send($chatId, trans('growth_companion.export_title')."\n".$json, [
                [['text' => trans('growth_companion.nav.today'), 'callback_data' => 'gc:home']],
            ]);

            return;
        }

        if ($payload === 'adv') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $next = $profile->isAdvanced() ? 'simple' : 'advanced';
            $this->service->setMode($profile, $next);
            $note = $next === 'advanced'
                ? trans('growth_companion.advanced_on')
                : trans('growth_companion.advanced_off');
            $this->sendSettings($messenger, $botItem, $chatId, $botUser, $note);

            return;
        }

        if ($payload === 'aic') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            $this->service->setAiConsent($profile, true);
            $this->sendSettings($messenger, $botItem, $chatId, $botUser, trans('growth_companion.ai_consent_on'));

            return;
        }

        if ($payload === 'ai') {
            $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
            if (!$profile->ai_consent) {
                $messenger->send($chatId, trans('growth_companion.ai_consent_needed'), [
                    [['text' => trans('growth_companion.btn_ai_consent'), 'callback_data' => 'gc:aic']],
                    [['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set']],
                ]);

                return;
            }
            $added = 0;
            $locale = $botItem->language_code ?: app()->getLocale();
            foreach ($this->service->enabledTopics($profile) as $topic) {
                $question = $this->service->questionForTopic($profile, $topic->template_slug, true);
                if ($question) {
                    $added += $this->service->generateAiVariants($question, $locale);
                }
            }
            $note = $added > 0
                ? trans('growth_companion.ai_variants_added', ['count' => $added])
                : trans('growth_companion.ai_unavailable');
            $this->sendSettings($messenger, $botItem, $chatId, $botUser, $note);
        }
    }

    private function handleBoardTap(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser,
        string $slug,
        ?string $callbackId,
        mixed $messageId
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $topic = $this->topicBySlug($profile, $slug);
        if (!$topic || !$topic->enabled) {
            $messenger->answerCallback($callbackId);

            return;
        }

        $status = $this->service->canOpenTopic($profile, $topic);
        if ($status !== 'ask') {
            $toast = match ($status) {
                'done_week' => trans('growth_companion.already_reviewed_week'),
                'budget' => trans('growth_companion.budget_full'),
                default => trans('growth_companion.already_reviewed_today'),
            };
            $messenger->answerCallback($callbackId, $toast);
            $rows = $this->boardKeyboard($profile);
            if ($messageId && $messenger->editReplyMarkup($chatId, $messageId, $rows)) {
                return;
            }
            $this->sendTopics($messenger, $profile, $chatId);

            return;
        }

        $messenger->answerCallback($callbackId);
        $question = $this->service->questionForTopic($profile, $slug);
        if (!$question) {
            $this->sendTopics($messenger, $profile, $chatId, trans('growth_companion.no_active_question'));

            return;
        }

        $variant = $this->service->pickVariant($question, $botUser->id, $botItem->language_code);
        if (!$variant) {
            $messenger->send($chatId, trans('growth_companion.error'), $this->boardKeyboard($profile));

            return;
        }

        $this->setState($botItem, $chatId, $type, self::STATE_AWAITING_ANSWER, [
            'question_id' => $question->id,
            'variant_id' => $variant->id,
            'topic' => $slug,
        ]);
        if ($question->schedule) {
            $this->service->markSent($question->schedule, $profile);
        }
        $this->sendQuestion($messenger, $chatId, $variant->body, $topic->displayLabel());
    }

    private function handleStart(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        if ($profile->onboarding_completed_at) {
            if ($profile->topics()->doesntExist()) {
                $this->service->ensureDefaultBoard($profile);
            }
            $this->sendHome($messenger, $profile, $chatId);

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
        $this->service->completeOnboarding($profile, $focus, $intensity, $notifyTime, $customFocus);
        $this->clearState($botItem, $chatId, $type);
        $profile->refresh();
        $this->sendHome($messenger, $profile, $chatId);
    }

    private function sendHome(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId, ?string $preamble = null): void
    {
        $cta = $this->primaryCta($profile);
        $html = $this->homeCardHtml($profile, $preamble);
        $messenger->send($chatId, $html, null, $this->navKeyboard($profile), 'HTML');
    }

    private function sendTopics(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId, ?string $preamble = null): void
    {
        $text = trans('growth_companion.topics_title');
        if ($preamble) {
            $text = $preamble."\n\n".$text;
        }
        $rows = $this->boardKeyboard($profile);
        $rows[] = [['text' => trans('growth_companion.btn_cadence'), 'callback_data' => 'gc:cad']];
        $rows[] = [['text' => trans('growth_companion.nav.more'), 'callback_data' => 'gc:more']];
        $messenger->send($chatId, $text, $rows, $this->navKeyboard($profile));
    }

    private function homeCardHtml(GrowthProfile $profile, ?string $preamble = null): string
    {
        $tz = $profile->timezone ?: 'Asia/Tehran';
        $now = \Carbon\Carbon::now($tz);
        $dow = trans('growth_companion.weekdays.'.$now->dayOfWeek);
        $checkin = $this->service->todayCheckin($profile);
        $stats = $this->service->weekCheckinStats($profile);
        $open = $this->service->nextOpenTopic($profile);
        $e = fn (string $v) => htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lines = [];
        if ($preamble) {
            $lines[] = $e($preamble);
            $lines[] = '';
        }
        $lines[] = '<b>'.$e(trans('growth_companion.home_title')).' — '.$e($dow).'</b>';
        $lines[] = '';
        if ($checkin && $checkin->isComplete()) {
            $mood = trans('growth_companion.mood.'.$checkin->mood);
            $lines[] = e(trans('growth_companion.home_energy', ['n' => (int) $checkin->energy])).' · '.$e($mood);
            $sleep = $checkin->sleep_hours
                ? trans('growth_companion.home_sleep', ['n' => (int) $checkin->sleep_hours])
                : trans('growth_companion.home_sleep_skip');
            $moved = $checkin->moved
                ? trans('growth_companion.home_moved_yes')
                : trans('growth_companion.home_moved_no');
            $lines[] = $e($sleep).' · '.$e($moved);
        } else {
            $lines[] = $e(trans('growth_companion.home_no_checkin'));
        }

        $focus = [];
        foreach ($this->service->enabledTopics($profile) as $topic) {
            if ($this->service->isTopicDone($profile, $topic)) {
                $focus[] = $topic->displayLabel();
            }
        }
        if ($focus !== []) {
            $lines[] = $e(trans('growth_companion.home_focus')).' '.$e(implode('، ', array_slice($focus, 0, 3)));
        }

        $lines[] = '';
        $lines[] = $e(trans('growth_companion.week_line', [
            'done' => $stats['days'],
            'total' => 7,
        ])).' '.$this->service->progressBar((int) $stats['days']);

        $lines[] = '';
        $lines[] = '<b>'.$e(trans('growth_companion.next_step')).'</b>';
        if ($open) {
            $lines[] = $e(trans('growth_companion.next_question_topic', ['topic' => $open->displayLabel()]));
        } elseif (!$checkin || !$checkin->isComplete()) {
            $lines[] = $e(trans('growth_companion.next_checkin'));
        } elseif (!$checkin->evening_note) {
            $lines[] = $e(trans('growth_companion.qotd_done'));
            $lines[] = $e(trans('growth_companion.next_evening'));
        } else {
            $lines[] = $e(trans('growth_companion.qotd_done'));
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{label: string, action: string, callback: ?string}
     */
    private function primaryCta(GrowthProfile $profile): array
    {
        $open = $this->service->nextOpenTopic($profile);
        if ($open) {
            return [
                'label' => trans('growth_companion.btn_answer_today'),
                'action' => 'q',
                'callback' => 'gc:q',
            ];
        }
        $checkin = $this->service->todayCheckin($profile);
        if (!$checkin || !$checkin->isComplete()) {
            return [
                'label' => trans('growth_companion.nav.checkin'),
                'action' => 'checkin',
                'callback' => 'gc:in',
            ];
        }
        if (!$checkin->evening_note) {
            return [
                'label' => trans('growth_companion.btn_evening'),
                'action' => 'eve',
                'callback' => 'gc:eve',
            ];
        }

        return [
            'label' => trans('growth_companion.nav.week'),
            'action' => 'week',
            'callback' => 'gc:rev',
        ];
    }

    private function navKeyboard(GrowthProfile $profile): array
    {
        $cta = $this->primaryCta($profile);

        return [
            [['text' => $cta['label']]],
            [
                ['text' => trans('growth_companion.nav.today')],
                ['text' => trans('growth_companion.nav.checkin')],
            ],
            [
                ['text' => trans('growth_companion.nav.ask')],
                ['text' => trans('growth_companion.nav.week')],
            ],
            [['text' => trans('growth_companion.nav.more')]],
        ];
    }

    private function navAction(string $text, GrowthProfile $profile): ?string
    {
        $cta = $this->primaryCta($profile);
        if ($text === $cta['label']) {
            return $cta['action'];
        }

        $map = [
            trans('growth_companion.nav.today') => 'home',
            trans('growth_companion.nav.checkin') => 'checkin',
            trans('growth_companion.nav.ask') => 'ask',
            trans('growth_companion.nav.week') => 'week',
            trans('growth_companion.nav.more') => 'more',
        ];

        return $map[$text] ?? null;
    }

    private function handleNav(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser,
        string $action
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        match ($action) {
            'q' => $this->openNextQuestion($messenger, $botItem, $type, $chatId, $botUser),
            'checkin' => $this->startCheckin($messenger, $chatId),
            'ask' => $this->startAsk($messenger, $botItem, $type, $chatId),
            'week' => $this->sendWeekly($messenger, $profile, $chatId),
            'more' => $this->sendMore($messenger, $botItem, $chatId, $botUser),
            'eve' => $this->startEvening($messenger, $botItem, $type, $chatId),
            default => $this->sendHome($messenger, $profile, $chatId),
        };
    }

    private function startAsk(GrowthMessenger $messenger, Bot $botItem, string $type, string $chatId): void
    {
        $this->setState($botItem, $chatId, $type, self::STATE_CUSTOM_QUESTION);
        $messenger->send($chatId, trans('growth_companion.ask_custom_question'), [
            [['text' => trans('growth_companion.btn_later'), 'callback_data' => 'gc:later']],
        ]);
    }

    private function startEvening(GrowthMessenger $messenger, Bot $botItem, string $type, string $chatId): void
    {
        $this->setState($botItem, $chatId, $type, self::STATE_EVENING);
        $messenger->send($chatId, trans('growth_companion.ask_evening'), [
            [['text' => trans('growth_companion.btn_later'), 'callback_data' => 'gc:later']],
        ]);
    }

    private function sendWeekly(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId): void
    {
        $messenger->send(
            $chatId,
            $this->service->weeklyReviewText($profile),
            [
                [['text' => trans('growth_companion.btn_save_review'), 'callback_data' => 'gc:revw']],
                [['text' => trans('growth_companion.nav.today'), 'callback_data' => 'gc:home']],
            ],
            $this->navKeyboard($profile),
            'HTML'
        );
    }

    private function startCheckin(GrowthMessenger $messenger, string $chatId): void
    {
        $messenger->send($chatId, trans('growth_companion.checkin_mood'), [
            [['text' => trans('growth_companion.mood.low'), 'callback_data' => 'gc:in:m:low']],
            [['text' => trans('growth_companion.mood.mid'), 'callback_data' => 'gc:in:m:mid']],
            [['text' => trans('growth_companion.mood.good'), 'callback_data' => 'gc:in:m:good']],
            [['text' => trans('growth_companion.mood.great'), 'callback_data' => 'gc:in:m:great']],
        ]);
    }

    private function handleCheckinChoice(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $chatId,
        BotUsers $botUser,
        string $payload
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $parts = explode(':', $payload);
        $step = $parts[0] ?? '';
        $value = $parts[1] ?? '';

        if ($step === 'm') {
            $this->service->upsertCheckin($profile, ['mood' => $value]);
            $messenger->send($chatId, trans('growth_companion.checkin_energy'), [
                [['text' => trans('growth_companion.energy.3'), 'callback_data' => 'gc:in:e:3']],
                [['text' => trans('growth_companion.energy.5'), 'callback_data' => 'gc:in:e:5']],
                [['text' => trans('growth_companion.energy.7'), 'callback_data' => 'gc:in:e:7']],
                [['text' => trans('growth_companion.energy.9'), 'callback_data' => 'gc:in:e:9']],
            ]);

            return;
        }
        if ($step === 'e') {
            $this->service->upsertCheckin($profile, ['energy' => (int) $value]);
            $messenger->send($chatId, trans('growth_companion.checkin_sleep'), [
                [['text' => trans('growth_companion.sleep.6'), 'callback_data' => 'gc:in:s:6']],
                [['text' => trans('growth_companion.sleep.7'), 'callback_data' => 'gc:in:s:7']],
                [['text' => trans('growth_companion.sleep.8'), 'callback_data' => 'gc:in:s:8']],
                [['text' => trans('growth_companion.btn_skip'), 'callback_data' => 'gc:in:s:x']],
            ]);

            return;
        }
        if ($step === 's') {
            $hours = $value === 'x' ? null : (int) $value;
            $this->service->upsertCheckin($profile, ['sleep_hours' => $hours]);
            $messenger->send($chatId, trans('growth_companion.checkin_move'), [
                [['text' => trans('growth_companion.move.yes'), 'callback_data' => 'gc:in:v:1']],
                [['text' => trans('growth_companion.move.no'), 'callback_data' => 'gc:in:v:0']],
                [['text' => trans('growth_companion.btn_skip'), 'callback_data' => 'gc:in:v:x']],
            ]);

            return;
        }
        if ($step === 'v') {
            $moved = $value === 'x' ? null : $value === '1';
            $this->service->upsertCheckin($profile, ['moved' => $moved]);
            $profile->refresh();
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.ack'));
        }
    }

    private function openNextQuestion(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $topic = $this->service->nextOpenTopic($profile);
        if (!$topic) {
            $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.qotd_done'));

            return;
        }
        $this->handleBoardTap($messenger, $botItem, $type, $chatId, $botUser, $topic->template_slug, null, null);
    }

    private function afterQuestionAnswered(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $type,
        string $chatId,
        BotUsers $botUser,
        GrowthProfile $profile
    ): void {
        $next = $this->service->nextOpenTopic($profile);
        if ($next) {
            $messenger->send($chatId, trans('growth_companion.ack'));
            $this->openNextQuestion($messenger, $botItem, $type, $chatId, $botUser);

            return;
        }
        $this->sendHome($messenger, $profile, $chatId, trans('growth_companion.ack'));
    }

    private function sendMore(GrowthMessenger $messenger, Bot $botItem, string $chatId, BotUsers $botUser): void
    {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $rows = [
            [['text' => trans('growth_companion.btn_topics'), 'callback_data' => 'gc:topics']],
            [['text' => trans('growth_companion.btn_history'), 'callback_data' => 'gc:hist']],
            [['text' => trans('growth_companion.btn_export'), 'callback_data' => 'gc:exp']],
            [['text' => trans('growth_companion.btn_privacy'), 'callback_data' => 'gc:priv']],
            [
                $this->intensityBtn('min', 'minimal', $profile->intensity ?: 'balanced'),
                $this->intensityBtn('bal', 'balanced', $profile->intensity ?: 'balanced'),
                $this->intensityBtn('act', 'active', $profile->intensity ?: 'balanced'),
            ],
            [
                ['text' => trans('growth_companion.btn_pause'), 'callback_data' => 'gc:p'],
                ['text' => trans('growth_companion.btn_unpause'), 'callback_data' => 'gc:u'],
            ],
        ];
        if ($profile->isAdvanced()) {
            $rows[] = [['text' => trans('growth_companion.btn_weekdays'), 'callback_data' => 'gc:wdm']];
            $rows[] = [['text' => trans('growth_companion.btn_ai_variants'), 'callback_data' => 'gc:ai']];
            $rows[] = [['text' => trans('growth_companion.btn_simple_mode'), 'callback_data' => 'gc:adv']];
        } else {
            $rows[] = [['text' => trans('growth_companion.btn_advanced'), 'callback_data' => 'gc:adv']];
        }
        $rows[] = [['text' => trans('growth_companion.nav.today'), 'callback_data' => 'gc:home']];
        $messenger->send($chatId, trans('growth_companion.more_title'), $rows, $this->navKeyboard($profile));
    }

    private function sendHistory(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId): void
    {
        $items = $this->service->recentResponses($profile, 10);
        if ($items->isEmpty()) {
            $messenger->send($chatId, trans('growth_companion.history_empty'), [
                [['text' => trans('growth_companion.nav.more'), 'callback_data' => 'gc:more']],
            ]);

            return;
        }
        $lines = ['<b>'.htmlspecialchars(trans('growth_companion.history_title'), ENT_QUOTES, 'UTF-8').'</b>', ''];
        $rows = [];
        foreach ($items as $response) {
            $label = $response->question?->program?->name ?: ($response->question?->domain ?: '');
            $when = optional($response->answered_at)?->timezone($profile->timezone ?: 'Asia/Tehran')?->format('m-d');
            $snippet = mb_substr(trim((string) $response->body), 0, 40);
            $lines[] = htmlspecialchars(trim($when.' · '.$label.' · '.$snippet), ENT_QUOTES, 'UTF-8');
            $rows[] = [[
                'text' => trans('growth_companion.btn_full_text').' '.$when,
                'callback_data' => 'gc:ft:'.$response->id,
            ]];
        }
        $rows[] = [['text' => trans('growth_companion.nav.more'), 'callback_data' => 'gc:more']];
        $messenger->send($chatId, implode("\n", $lines), $rows, null, 'HTML');
    }

    private function sendFullText(GrowthMessenger $messenger, BotUsers $botUser, Bot $botItem, string $chatId, int $id): void
    {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $response = \App\Models\GrowthResponse::find($id);
        $review = $id && !$response ? \App\Models\GrowthReview::find($id) : null;
        $body = $response?->body ?? $review?->body ?? trans('growth_companion.error');
        $messenger->send($chatId, $body, [
            [['text' => trans('growth_companion.nav.today'), 'callback_data' => 'gc:home']],
        ]);
    }

    private function sendReviewSummary(GrowthMessenger $messenger, \App\Models\GrowthReview $review, string $chatId): void
    {
        $bullets = $this->service->bulletSummary((string) $review->body);
        $lines = ['<b>'.htmlspecialchars(trans('growth_companion.weekly_review_saved'), ENT_QUOTES, 'UTF-8').'</b>', ''];
        foreach ($bullets as $bullet) {
            $lines[] = '✓ '.htmlspecialchars($bullet, ENT_QUOTES, 'UTF-8');
        }
        $messenger->send($chatId, implode("\n", $lines), [
            [['text' => trans('growth_companion.btn_full_text'), 'callback_data' => 'gc:ft:'.$review->id]],
            [['text' => trans('growth_companion.nav.today'), 'callback_data' => 'gc:home']],
        ], null, 'HTML');
    }

    private function boardKeyboard(GrowthProfile $profile): array
    {
        $rows = [];
        $row = [];
        foreach ($this->service->enabledTopics($profile) as $topic) {
            $done = $this->service->isTopicDone($profile, $topic);
            $row[] = [
                'text' => ($done ? '✅ ' : '☐ ').$topic->displayLabel(),
                'callback_data' => 'gc:b:'.$topic->template_slug,
            ];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        $rows[] = [
            ['text' => trans('growth_companion.btn_add_topic'), 'callback_data' => 'gc:add'],
            ['text' => trans('growth_companion.btn_remove_topic'), 'callback_data' => 'gc:del'],
        ];
        $rows[] = [
            ['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set'],
        ];

        return $rows;
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

    private function sendSettings(
        GrowthMessenger $messenger,
        Bot $botItem,
        string $chatId,
        BotUsers $botUser,
        ?string $preamble = null
    ): void {
        $profile = $this->service->getOrCreateProfile($botUser, $botItem->id);
        $current = $profile->intensity ?: 'balanced';
        $text = trans('growth_companion.settings_title');
        if ($preamble) {
            $text = $preamble."\n\n".$text;
        }

        $rows = [
            [
                $this->intensityBtn('min', 'minimal', $current),
                $this->intensityBtn('bal', 'balanced', $current),
                $this->intensityBtn('act', 'active', $current),
            ],
            [
                ['text' => trans('growth_companion.btn_daily'), 'callback_data' => 'gc:freq:d'],
                ['text' => trans('growth_companion.btn_weekly'), 'callback_data' => 'gc:freq:w'],
            ],
            [
                ['text' => trans('growth_companion.btn_pause'), 'callback_data' => 'gc:p'],
                ['text' => trans('growth_companion.btn_unpause'), 'callback_data' => 'gc:u'],
            ],
            [['text' => trans('growth_companion.btn_cadence'), 'callback_data' => 'gc:cad']],
            [['text' => trans('growth_companion.btn_weekly_review'), 'callback_data' => 'gc:rev']],
            [['text' => trans('growth_companion.btn_export'), 'callback_data' => 'gc:exp']],
            [['text' => trans('growth_companion.btn_custom_question'), 'callback_data' => 'gc:cq']],
            [['text' => trans('growth_companion.btn_privacy'), 'callback_data' => 'gc:priv']],
            [['text' => trans('growth_companion.btn_board'), 'callback_data' => 'gc:home']],
        ];

        if ($profile->isAdvanced()) {
            $rows[] = [['text' => trans('growth_companion.btn_weekdays'), 'callback_data' => 'gc:wdm']];
            $rows[] = [['text' => trans('growth_companion.btn_ai_variants'), 'callback_data' => 'gc:ai']];
            $rows[] = [['text' => trans('growth_companion.btn_simple_mode'), 'callback_data' => 'gc:adv']];
        } else {
            $rows[] = [['text' => trans('growth_companion.btn_advanced'), 'callback_data' => 'gc:adv']];
        }

        $messenger->send($chatId, $text, $rows);
    }

    private function intensityBtn(string $code, string $intensity, string $current): array
    {
        $label = trans('growth_companion.intensity.'.$intensity);
        if ($current === $intensity) {
            $label = '✓ '.$label;
        }

        return ['text' => $label, 'callback_data' => 'gc:int:'.$code];
    }

    private function sendAddTopicMenu(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId): void
    {
        $slugs = $this->service->addableSlugs($profile);
        if ($slugs === []) {
            $messenger->send($chatId, trans('growth_companion.no_topics_to_add'), $this->boardKeyboard($profile));

            return;
        }

        $rows = [];
        $row = [];
        foreach ($slugs as $slug) {
            $row[] = [
                'text' => trans('growth_companion.focus.'.$slug) === 'growth_companion.focus.'.$slug
                    ? $slug
                    : trans('growth_companion.focus.'.$slug),
                'callback_data' => 'gc:a:'.$slug,
            ];
            if (count($row) === 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [['text' => trans('growth_companion.btn_board'), 'callback_data' => 'gc:home']];
        $messenger->send($chatId, trans('growth_companion.pick_add_topic'), $rows);
    }

    private function sendRemoveTopicMenu(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId): void
    {
        $topics = $this->service->enabledTopics($profile);
        if ($topics->isEmpty()) {
            $messenger->send($chatId, trans('growth_companion.no_topics_to_remove'), $this->boardKeyboard($profile));

            return;
        }

        $rows = [];
        foreach ($topics as $topic) {
            $rows[] = [[
                'text' => $topic->displayLabel(),
                'callback_data' => 'gc:x:'.$topic->template_slug,
            ]];
        }
        $rows[] = [['text' => trans('growth_companion.btn_board'), 'callback_data' => 'gc:home']];
        $messenger->send($chatId, trans('growth_companion.pick_remove_topic'), $rows);
    }

    private function sendCadenceMenu(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId, ?string $preamble = null): void
    {
        $text = trans('growth_companion.cadence_menu');
        if ($preamble) {
            $text = $preamble."\n\n".$text;
        }
        $rows = [];
        foreach ($this->service->enabledTopics($profile) as $topic) {
            $mark = $topic->cadence === 'weekly'
                ? trans('growth_companion.btn_weekly')
                : trans('growth_companion.btn_daily');
            $rows[] = [[
                'text' => $mark.' · '.$topic->displayLabel(),
                'callback_data' => 'gc:c:'.$topic->template_slug,
            ]];
        }
        $rows[] = [['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set']];
        $messenger->send($chatId, $text, $rows);
    }

    private function sendWeekdayTopicMenu(GrowthMessenger $messenger, GrowthProfile $profile, string $chatId): void
    {
        $rows = [];
        foreach ($this->service->enabledTopics($profile) as $topic) {
            $rows[] = [[
                'text' => $topic->displayLabel(),
                'callback_data' => 'gc:w:'.$topic->template_slug,
            ]];
        }
        $rows[] = [['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set']];
        $messenger->send($chatId, trans('growth_companion.weekday_menu'), $rows);
    }

    private function sendWeekdayPicker(
        GrowthMessenger $messenger,
        GrowthProfile $profile,
        string $chatId,
        string $slug,
        ?string $preamble = null
    ): void {
        $topic = $this->topicBySlug($profile, $slug, true);
        $selected = array_map('intval', $topic?->weekdays ?? []);
        $text = trans('growth_companion.weekday_menu');
        if ($preamble) {
            $text = $preamble."\n\n".$text;
        }
        $rows = [];
        $row = [];
        for ($day = 0; $day <= 6; $day++) {
            $label = trans('growth_companion.weekdays.'.$day);
            if (in_array($day, $selected, true)) {
                $label = '✓ '.$label;
            }
            $row[] = ['text' => $label, 'callback_data' => 'gc:k:'.$slug.':'.$day];
            if (count($row) === 4) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }
        $rows[] = [['text' => trans('growth_companion.btn_settings'), 'callback_data' => 'gc:set']];
        $messenger->send($chatId, $text, $rows);
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

    private function restartKeyboard(): array
    {
        return [
            [['text' => trans('growth_companion.btn_restart'), 'callback_data' => 'gc:ask']],
        ];
    }

    private function topicBySlug(GrowthProfile $profile, string $slug, bool $includeDisabled = false): ?GrowthProfileTopic
    {
        $query = $profile->topics()->where('template_slug', $slug);
        if (!$includeDisabled) {
            $query->where('enabled', true);
        }

        return $query->first();
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
