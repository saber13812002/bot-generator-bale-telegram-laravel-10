<?php

namespace App\Http\Controllers;

use App\Helpers\BotHelper;
use App\Helpers\DateHelper;
use App\Helpers\IranProvincesHelper;
use App\Interfaces\Services\MawkibFinderService;
use App\Models\Bot;
use App\Models\BotUsers;
use App\Modules\BaleOtp\Support\PhoneNormalizer;
use App\Services\MawkibFinderOtpService;
use Carbon\Carbon;
use Exception;
use Hekmatinasser\Verta\Verta;
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
    private const STEP_WAITING_CONFIRM = 'waiting_confirm';

    public function __construct(
        private MawkibFinderService $mawkibFinderService,
        private MawkibFinderOtpService $mawkibFinderOtpService,
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
                $this->handleCallbackQuery($bot, $update['callback_query'], $type, $botId);
                return response()->json(['status' => 'ok'], 200);
            }

            $message = $update['message'] ?? $update['edited_message'] ?? null;
            if (!$message) {
                return response()->json(['status' => 'ok'], 200);
            }

            $chatId = (int) ($message['chat']['id'] ?? $bot->ChatID());
            if ($chatId < 0) {
                return response()->json(['status' => 'ok'], 200);
            }

            if (isset($message['contact'])) {
                $this->handleContactMessage($bot, $message, $chatId, $type, $botId);
                return response()->json(['status' => 'ok'], 200);
            }

            $text = trim($message['text'] ?? $bot->Text() ?? '');
            if ($text !== '') {
                $this->handleTextMessage($bot, $text, $chatId, $type, $botId);
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
    ): void {
        $botUser = $this->getOrCreateBotUser($chatId, $botId, $type);
        $step = $botUser->setting('mawkib_step', self::STEP_START);

        if ($text === '/start' || str_starts_with($text, '/start ')) {
            $this->handleStart($bot, $botUser, $type, $chatId);
            return;
        }

        match ($step) {
            self::STEP_WAITING_PHONE => $this->handlePhoneInput($bot, $botUser, $text, $type, $chatId),
            self::STEP_WAITING_OTP => $this->handleOtpInput($bot, $botUser, $text, $chatId),
            self::STEP_WAITING_NATIONAL_CODE => $this->handleNationalCode($bot, $botUser, $text, $chatId),
            default => BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_use_start')),
        };
    }

    private function handleContactMessage(
        Telegram $bot,
        array $message,
        int $chatId,
        string $type,
        int $botId,
    ): void {
        $botUser = $this->getOrCreateBotUser($chatId, $botId, $type);

        if ($botUser->setting('mawkib_step') !== self::STEP_WAITING_PHONE) {
            return;
        }

        $phone = $message['contact']['phone_number'] ?? null;
        if (!$phone) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_invalid_phone'));
            return;
        }

        $this->processPhoneAndSendOtp($bot, $botUser, $phone, $type, $chatId);
    }

    private function handleCallbackQuery(
        Telegram $bot,
        array $callbackQuery,
        string $type,
        int $botId,
    ): void {
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        $data = (string) ($callbackQuery['data'] ?? '');

        $bot->answerCallbackQuery(['callback_query_id' => $callbackQuery['id']]);

        $botUser = $this->getOrCreateBotUser($chatId, $botId, $type);

        if ($data === 'mawkib_restart') {
            $this->handleStart($bot, $botUser, $type, $chatId);
            return;
        }

        if ($data === 'mawkib_confirm') {
            $this->handleConfirmSearch($bot, $botUser, $chatId);
            return;
        }

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
        $this->mawkibFinderOtpService->clearOtpSession($botUser);

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_PHONE,
            'mawkib_verified_phone' => null,
            'mawkib_national_code' => null,
            'mawkib_province' => null,
            'mawkib_entry_date' => null,
            'mawkib_stay_days' => null,
            'mawkib_from_date' => null,
            'mawkib_to_date' => null,
        ]);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_welcome'));
        $this->sendPhoneRequestKeyboard($bot, $chatId, $type);
    }

    private function handlePhoneInput(Telegram $bot, BotUsers $botUser, string $text, string $type, int $chatId): void
    {
        $this->processPhoneAndSendOtp($bot, $botUser, $text, $type, $chatId);
    }

    private function processPhoneAndSendOtp(
        Telegram $bot,
        BotUsers $botUser,
        string $phone,
        string $type,
        int $chatId,
    ): void {
        if ($type !== 'bale') {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_bale_only'));
            return;
        }

        $normalized = PhoneNormalizer::normalize($phone);
        if ($normalized === null) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_invalid_phone'));
            $this->sendPhoneRequestKeyboard($bot, $chatId, $type);
            return;
        }

        $this->removeReplyKeyboard($bot, $chatId);

        $result = $this->mawkibFinderOtpService->sendOtp($botUser, $phone);
        if (!$result['success']) {
            BotHelper::sendMessageByChatId($bot, $chatId, $result['message']);
            $this->sendPhoneRequestKeyboard($bot, $chatId, $type);
            return;
        }

        $botUser->settings(['mawkib_step' => self::STEP_WAITING_OTP]);
        BotHelper::sendMessageByChatId($bot, $chatId, $result['message']);
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_ask_otp'));
    }

    private function handleOtpInput(Telegram $bot, BotUsers $botUser, string $text, int $chatId): void
    {
        $otp = preg_replace('/\D+/', '', $text) ?? '';
        if ($otp === '') {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_otp_invalid'));
            return;
        }

        $result = $this->mawkibFinderOtpService->verifyOtp($botUser, $otp);
        if (!$result['success']) {
            BotHelper::sendMessageByChatId($bot, $chatId, $result['message']);
            return;
        }

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_NATIONAL_CODE,
            'mawkib_verified_phone' => $result['phone'],
        ]);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_phone_verified'));
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_ask_national_code'));
    }

    private function handleNationalCode(Telegram $bot, BotUsers $botUser, string $text, int $chatId): void
    {
        $nationalCode = $this->normalizeNationalCode($text);

        if (!$this->isValidNationalCode($nationalCode)) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_invalid_national_code'));
            return;
        }

        $phone = $botUser->setting('mawkib_verified_phone');
        if (!$phone) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            $this->handleStart($bot, $botUser, 'bale', $chatId);
            return;
        }

        $verified = $this->mawkibFinderService->verifyIdentity($phone, $nationalCode);

        if (!$verified) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_identity_not_found', [
                'mobile' => $this->formatPhoneForDisplay($phone),
                'national_code' => $nationalCode,
            ]));
            $botUser->settings(['mawkib_step' => self::STEP_WAITING_NATIONAL_CODE]);
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
        $this->sendAvailabilityResults($bot, $chatId, $province, $results, null, null);

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_province_availability_intro', [
            'province' => $province,
        ]));

        $this->sendRegistrationLink($bot, $chatId);
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_today_result_hint'));
        $this->sendEntryDateKeyboard($bot, $chatId);
        $this->sendRestartInlineButton($bot, $chatId);
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

        $displayDate = $this->formatShamsiDateLabel(Carbon::parse($date), 0);
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

        $entryDate = $botUser->setting('mawkib_entry_date');
        if (!$entryDate) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $from = Carbon::parse($entryDate);
        $to = $from->copy()->addDays($days);
        $fromFormatted = $from->format('Y/m/d');
        $toFormatted = $to->format('Y/m/d');

        $botUser->settings([
            'mawkib_step' => self::STEP_WAITING_CONFIRM,
            'mawkib_stay_days' => $days,
            'mawkib_from_date' => $fromFormatted,
            'mawkib_to_date' => $toFormatted,
        ]);

        $message = trans('bot.mawkib_finder_confirm_summary', [
            'from' => DateHelper::toShamsi($from, 'd F Y'),
            'to' => DateHelper::toShamsi($to, 'd F Y'),
            'days' => $days,
        ]);

        $keyboard = $bot->buildInlineKeyBoard([
            [
                $bot->buildInlineKeyBoardButton(trans('bot.mawkib_finder_confirm_button'), callback_data: 'mawkib_confirm'),
                $bot->buildInlineKeyBoardButton(trans('bot.mawkib_finder_restart_button'), callback_data: 'mawkib_restart'),
            ],
        ]);

        BotHelper::sendKeyboardMessageToChatId($bot, $message, $keyboard, $chatId);
    }

    private function handleConfirmSearch(Telegram $bot, BotUsers $botUser, int $chatId): void
    {
        $province = $botUser->setting('mawkib_province');
        $fromDate = $botUser->setting('mawkib_from_date');
        $toDate = $botUser->setting('mawkib_to_date');

        if (!$province || !$fromDate || !$toDate) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_error'));
            return;
        }

        $results = $this->mawkibFinderService->getAvailability($province, $fromDate, $toDate);

        $fromShamsi = DateHelper::toShamsi(Carbon::createFromFormat('Y/m/d', $fromDate), 'd F Y');
        $toShamsi = DateHelper::toShamsi(Carbon::createFromFormat('Y/m/d', $toDate), 'd F Y');

        $this->sendAvailabilityResults($bot, $chatId, $province, $results, $fromShamsi, $toShamsi);

        $this->sendRegistrationLink($bot, $chatId);

        $botUser->settings(['mawkib_step' => self::STEP_START]);
        $this->sendRestartInlineButton($bot, $chatId);
    }

    private function sendPhoneRequestKeyboard(Telegram $bot, int $chatId, string $type): void
    {
        if ($type === 'bale') {
            $keyboard = json_encode([
                'keyboard' => [
                    [
                        ['text' => trans('bot.mawkib_finder_send_phone_button'), 'request_contact' => true],
                    ],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ], JSON_UNESCAPED_UNICODE);

            BotHelper::sendKeyboardMessageToChatId(
                $bot,
                trans('bot.mawkib_finder_ask_phone'),
                $keyboard,
                $chatId,
            );
            return;
        }

        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_ask_phone_type'));
    }

    private function removeReplyKeyboard(Telegram $bot, int $chatId): void
    {
        $keyboard = json_encode(['remove_keyboard' => true]);
        BotHelper::sendKeyboardMessageToChatId($bot, ' ', $keyboard, $chatId);
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

        for ($i = 0; $i < 14; $i++) {
            $date = Carbon::today()->addDays($i);
            $label = $this->formatShamsiDateLabel($date, $i);
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

    private function sendRestartInlineButton(Telegram $bot, int $chatId): void
    {
        $keyboard = $bot->buildInlineKeyBoard([
            [$bot->buildInlineKeyBoardButton(trans('bot.mawkib_finder_restart_button'), callback_data: 'mawkib_restart')],
        ]);
        BotHelper::sendKeyboardMessageToChatId(
            $bot,
            trans('bot.mawkib_finder_restart_hint'),
            $keyboard,
            $chatId,
        );
    }

    private function sendRegistrationLink(Telegram $bot, int $chatId): void
    {
        $registrationUrl = config('mawkib_finder.registration_url');
        BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_register_urgency', [
            'url' => $registrationUrl,
        ]));
    }

    /**
     * @param array<int, array{city: string, vacant_count: int}> $results
     */
    private function sendAvailabilityResults(
        Telegram $bot,
        int $chatId,
        string $province,
        array $results,
        ?string $fromShamsi,
        ?string $toShamsi,
    ): void {
        if ($results === []) {
            BotHelper::sendMessageByChatId($bot, $chatId, trans('bot.mawkib_finder_no_availability'));
            return;
        }

        if ($fromShamsi && $toShamsi) {
            $message = trans('bot.mawkib_finder_final_with_dates', [
                'province' => $province,
                'from' => $fromShamsi,
                'to' => $toShamsi,
            ]) . "\n\n";
        } else {
            $message = trans('bot.mawkib_finder_results_header', ['province' => $province]) . "\n\n";
        }

        foreach ($results as $item) {
            $message .= trans('bot.mawkib_finder_city_line', [
                'city' => $item['city'],
                'count' => $item['vacant_count'],
            ]) . "\n";
        }

        BotHelper::sendMessageByChatId($bot, $chatId, trim($message));
    }

    private function formatShamsiDateLabel(Carbon $date, int $offsetFromToday): string
    {
        $verta = Verta::instance($date);
        $dayName = $verta->format('l');
        $datePart = $verta->format('d F');

        return match ($offsetFromToday) {
            0 => trans('bot.mawkib_finder_today_label', ['day' => $dayName, 'date' => $datePart]),
            1 => trans('bot.mawkib_finder_tomorrow_label', ['day' => $dayName, 'date' => $datePart]),
            2 => trans('bot.mawkib_finder_day_after_label', ['day' => $dayName, 'date' => $datePart]),
            default => trans('bot.mawkib_finder_day_label', ['day' => $dayName, 'date' => $datePart]),
        };
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
