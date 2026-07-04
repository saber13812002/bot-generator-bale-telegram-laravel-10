<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\DateHelper;
use App\Helpers\IranProvincesHelper;
use App\Interfaces\Services\MawkibFinderService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use App\Modules\BotOwner\Contracts\BotOwnerRepositoryInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram;

class MawkibFinderController extends Controller
{
    private const STEP_START = 'start';
    private const STEP_WAITING_PHONE = 'waiting_phone';
    private const STEP_WAITING_OTP = 'waiting_otp';
    private const STEP_WAITING_NATIONAL_CODE = 'waiting_national_code';
    private const STEP_WAITING_PROVINCE = 'waiting_province';
    private const STEP_WAITING_ENTRY_DATE = 'waiting_entry_date';
    private const STEP_WAITING_STAY_DAYS = 'waiting_stay_days';

    public function __construct(
        private MawkibFinderService $mawkibFinderService,
        private BotOwnerRepositoryInterface $botOwnerRepository,
        private \App\Modules\BotOwner\Contracts\BotOwnerAuthServiceInterface $authService,
    ) {}

    public function webhook(Request $request)
    {
        Log::info('[MawkibFinder] Webhook received', [
            'origin' => $request->input('origin'),
            'bot_id' => $request->input('bot_id'),
        ]);

        try {
            $type = $request->input('origin', 'telegram');
            $botId = (int) $request->input('bot_id');
            $botMotherId = (int) $request->input('bot_mother_id', 1);

            $bot = $this->createBotInstance($request, $type, $botId);
            if (!$bot) {
                Log::error('[MawkibFinder] Could not create bot instance');
                return response()->json(['status' => 'error'], 200);
            }

            $update = $request->json()->all() ?? $request->all();

            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botId, $botMotherId);
                return response()->json(['status' => 'ok'], 200);
            }

            $chatId = $bot->ChatID();
            $text = trim($bot->Text() ?? '');

            if ($chatId < 0) {
                return response()->json(['status' => 'ok'], 200);
            }

            if ($text !== '') {
                $this->handleTextMessage($bot, $text, $chatId, $type, $botId, $botMotherId);
            }

            return response()->json(['status' => 'ok'], 200);
        } catch (Exception $e) {
            Log::error('[MawkibFinder] Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    private function createBotInstance(Request $request, string $type, ?int $botId): ?Telegram
    {
        $token = null;

        if ($request->has('token')) {
            $token = $request->input('token');
        } elseif ($botId) {
            $botModel = Bot::find($botId);
            if ($botModel) {
                $token = $type === 'bale' ? $botModel->bale_bot_token : $botModel->telegram_bot_token;
            }
        }

        if (!$token) {
            return null;
        }

        return $type === 'bale' ? new Telegram($token, 'bale') : new Telegram($token);
    }

    private function handleTextMessage(
        Telegram $bot,
        string $text,
        int $chatId,
        string $type,
        int $botId,
        int $botMotherId,
    ): void {
        $botUser = $this->getOrCreateBotUser($chatId, $botId, $type);
        $step = $botUser->setting('mawkib_step', self::STEP_START);

        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->handleStart($bot, $botUser, $type, $chatId);
            return;
        }

        if ($step === self::STEP_WAITING_PHONE) {
            $this->handlePhoneInput($bot, $botUser, $text, $type, $chatId);
            return;
        }

        if ($step === self::STEP_WAITING_OTP) {
            $this->handleOtpInput($bot, $botUser, $text, $type, $chatId);
            return;
        }

        if ($step === self::STEP_WAITING_NATIONAL_CODE) {
            $this->handleNationalCode($bot, $botUser, $text, $type, $chatId);
            return;
        }

        BotHelper::sendMessage($bot, trans('bot.mawkib_finder_use_start'));
    }

    private function handleCallbackQuery(
        Telegram $bot,
        array $callbackQuery,
        string $type,
        int $botId,
        int $botMotherId,
    ): void {
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        $data = (string) ($callbackQuery['data'] ?? '');

        $bot->answerCallbackQuery(['callback_query_id' => $callbackQuery['id']]);

        $botUser = $this->getOrCreateBotUser($chatId, $botId, $type);

        if (str_starts_with($data, 'mawkib_province_')) {
            $index = (int) str_replace('mawkib_province_', '', $data);
            $this->handleProvinceSelection($bot, $botUser, $index, $chatId);
            return;
        }

        if (str_starts_with($data, 'mawkib_date_')) {
            $date = str_replace('mawkib_date_', '', $data);
            $this->handleEntryDateSelection($bot, $botUser, $date, $chatId);
            return;
        }

        if (str_starts_with($data, 'mawkib_stay_')) {
            $days = (int) str_replace('mawkib_stay_', '', $data);
            $this->handleStayDaysSelection($bot, $botUser, $days, $chatId);
        }
    }

    private function handleStart(Telegram $bot, BotUsers $botUser, string $type, int $chatId): void
    {
        $phone = $this->resolveVerifiedPhone($botUser, (string) $chatId, $type);

        if (!$phone) {
            // Ask for phone number to send OTP
            $botUser->settings(['mawkib_step' => self::STEP_WAITING_PHONE]);
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_ask_phone'));
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_NATIONAL_CODE,
            'mawkib_verified_phone' => $phone,
            'mawkib_national_code' => null,
            'mawkib_province' => null,
            'mawkib_entry_date' => null,
            'mawkib_stay_days' => null,
        ]);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_welcome'));
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_ask_national_code'));
    }

    private function handlePhoneInput(Telegram $bot, BotUsers $botUser, string $text, string $type, int $chatId): void
    {
        $phone = PhoneNormalizer::normalize($text);
        if (!$phone) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_invalid_phone'));
            return;
        }

        // Send OTP via BaleOtpService
        $result = $this->authService->sendOtp($phone);

        if (!$result['success']) {
            BotHelper::sendMessageByChatId($bot, $chatId, $result['message']);
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_OTP,
            'mawkib_otp_phone' => $phone,
        ]);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_otp_sent'));
    }

    private function handleOtpInput(Telegram $bot, BotUsers $botUser, string $text, string $type, int $chatId): void
    {
        $phone = $botUser->setting('mawkib_otp_phone');
        if (!$phone) {
            $botUser->settings(['mawkib_step' => self::STEP_START]);
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $result = $this->authService->verifyOtp($phone, trim($text));

        if (!$result['success']) {
            BotHelper::sendMessageByChatId($bot, $chatId, $result['message']);
            return;
        }

        // OTP verified - store phone and proceed
        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_NATIONAL_CODE,
            'mawkib_verified_phone' => $phone,
        ]);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_phone_verified'));
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_ask_national_code'));
    }

    private function handleNationalCode(Telegram $bot, BotUsers $botUser, string $text, string $type, int $chatId): void
    {
        $nationalCode = $this->normalizeNationalCode($text);

        if (!$this->isValidNationalCode($nationalCode)) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_invalid_national_code'));
            return;
        }

        $phone = $botUser->setting('mawkib_verified_phone') ?? $this->resolveVerifiedPhone($botUser, (string) $chatId, $type);
        if (!$phone) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_mobile_not_verified'));
            return;
        }

        $verified = $this->mawkibFinderService->verifyIdentity($phone, $nationalCode);

        if (!$verified) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_identity_not_found', [
                'mobile' => $this->formatPhoneForDisplay($phone),
                'national_code' => $nationalCode,
            ]));
            $botUser->settings(['mawkib_step' => self::STEP_START]);
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_PROVINCE,
            'mawkib_national_code' => $nationalCode,
        ]);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_verified_ok'));
        $this->sendProvinceKeyboard($bot, $chatId);
    }

    private function handleProvinceSelection(Telegram $bot, BotUsers $botUser, int $index, int $chatId): void
    {
        $province = IranProvincesHelper::nameByIndex($index);
        if ($province === null) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_ENTRY_DATE,
            'mawkib_province' => $province,
        ]);

        $results = $this->mawkibFinderService->getAvailability($province);
        $this->sendAvailabilityResults($bot, $chatId, $province, $results, false);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_today_result_hint'));
        $this->sendEntryDateKeyboard($bot, $chatId);
    }

    private function handleEntryDateSelection(Telegram $bot, BotUsers $botUser, string $date, int $chatId): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_STAY_DAYS,
            'mawkib_entry_date' => $date,
        ]);

        $displayDate = DateHelper::toShamsi($date, 'd F Y');
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_select_stay_days', [
            'date' => $displayDate,
        ]));

        $this->sendStayDaysKeyboard($bot, $chatId);
    }

    private function handleStayDaysSelection(Telegram $bot, BotUsers $botUser, int $days, int $chatId): void
    {
        if ($days < 1 || $days > 14) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $province = $botUser->setting('mawkib_province');
        $entryDate = $botUser->setting('mawkib_entry_date');

        if (!$province || !$entryDate) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_START,
            'mawkib_stay_days' => $days,
        ]);

        $results = $this->mawkibFinderService->getAvailability($province, $entryDate, $days);
        $this->sendAvailabilityResults($bot, $chatId, $province, $results, true);

        $registrationUrl = config('mawkib_finder.registration_url');
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_registration_link', [
            'url' => $registrationUrl,
        ]));
    }

    private function sendProvinceKeyboard(Telegram $bot, int $chatId): void
    {
        $buttons = [];
        $row = [];

        foreach (IranProvincesHelper::all() as $index => $name) {
            $row[] = $bot->buildInlineKeyBoardButton($name, callback_data: 'mawkib_province_' . $index);
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }

        if ($row !== []) {
            $buttons[] = $row;
        }

        $keyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessageToChatId(
            $bot,
            trans('bot.mawkib_finder_select_province'),
            $keyboard,
            $chatId,
        );
    }

    private function sendEntryDateKeyboard(Telegram $bot, int $chatId): void
    {
        $buttons = [];
        $row = [];

        for ($i = 1; $i <= 14; $i++) {
            $date = Carbon::today()->addDays($i);
            $label = DateHelper::toShamsi($date, 'd F');
            $row[] = $bot->buildInlineKeyBoardButton($label, callback_data: 'mawkib_date_' . $date->format('Y-m-d'));

            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }

        if ($row !== []) {
            $buttons[] = $row;
        }

        $keyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessageToChatId(
            $bot,
            trans('bot.mawkib_finder_select_entry_date'),
            $keyboard,
            $chatId,
        );
    }

    private function sendStayDaysKeyboard(Telegram $bot, int $chatId): void
    {
        $buttons = [];
        $row = [];

        for ($day = 1; $day <= 14; $day++) {
            $row[] = $bot->buildInlineKeyBoardButton(
                trans('bot.mawkib_finder_stay_day_label', ['days' => $day]),
                callback_data: 'mawkib_stay_' . $day,
            );

            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }

        if ($row !== []) {
            $buttons[] = $row;
        }

        $keyboard = $bot->buildInlineKeyBoard($buttons);
        BotHelper::sendKeyboardMessageToChatId(
            $bot,
            trans('bot.mawkib_finder_select_stay_days_prompt'),
            $keyboard,
            $chatId,
        );
    }

    /**
     * @param array<int, array{city: string, vacant_count: int}> $results
     */
    private function sendAvailabilityResults(
        Telegram $bot,
        int $chatId,
        string $province,
        array $results,
        bool $isFinal,
    ): void {
        if ($results === []) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_no_availability'));
            return;
        }

        $headerKey = $isFinal ? 'bot.mawkib_finder_final_results' : 'bot.mawkib_finder_results_header';
        $message = trans($headerKey, ['province' => $province]) . "\n\n";

        foreach ($results as $item) {
            $message .= trans('bot.mawkib_finder_city_line', [
                'city' => $item['city'],
                'count' => $item['vacant_count'],
            ]) . "\n";
        }

        BotHelper::sendMessageByChatId($bot, $chatId, trim($message));
    }

    private function getOrCreateBotUser(int $chatId, int $botId, string $type): BotUsers
    {
        $botUser = BotUsers::where('chat_id', $chatId)
            ->where('origin', $type)
            ->where('bot_id', $botId)
            ->first();

        if ($botUser) {
            return $botUser;
        }

        return BotUsers::create([
            'chat_id' => $chatId,
            'bot_id' => $botId,
            'origin' => $type,
            'status' => 'active',
        ]);
    }

    private function resolveVerifiedPhone(BotUsers $botUser, string $chatId, string $type): ?string
    {
        $stored = $botUser->setting('verified_phone') ?? $botUser->setting('mawkib_verified_phone');
        if ($stored) {
            return PhoneNormalizer::normalize($stored);
        }

        if ($type === 'bale') {
            $owner = $this->botOwnerRepository->findByBaleChatId($chatId);
            if ($owner?->phone) {
                return PhoneNormalizer::normalize($owner->phone);
            }
        }

        return null;
    }

    private function normalizeNationalCode(string $text): string
    {
        $digits = preg_replace('/\D+/', '', $text) ?? '';

        return str_pad($digits, 10, '0', STR_PAD_LEFT);
    }

    private function isValidNationalCode(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code)) {
            return false;
        }

        if (preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return ($remainder < 2 && $check === $remainder) || ($remainder >= 2 && $check === 11 - $remainder);
    }

    private function formatPhoneForDisplay(string $phone): string
    {
        if (str_starts_with($phone, '98') && strlen($phone) === 12) {
            return '0' . substr($phone, 2);
        }

        return $phone;
    }
}
