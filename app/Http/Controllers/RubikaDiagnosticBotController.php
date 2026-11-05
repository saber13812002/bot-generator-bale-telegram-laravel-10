<?php

namespace App\Http\Controllers;

use App\Helpers\AdminHelper;
use App\Helpers\TokenHelper;
use App\Http\Requests\BotRequest;
use App\Models\Bot;
use App\Models\BotLog;
use App\Services\Rubika\RubikaUpdateNormalizer;
use App\Services\RubikaBotClient;
use App\Services\SyncPlatformRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ربات تشخیصی روبیکا — BM-RUBIKA-DIAGNOSTIC-BOT
 *
 * نوع ربات جدید BotMother (endpoint_id: rubika-diagnostic) که رفتار
 * GetChatID را برای پلتفرم روبیکا با جزئیات بیشتر ارائه می‌دهد:
 *
 *  - پیام خصوصی:  Chat ID، User ID (sender)، نام (اگر API بدهد)،
 *    Username (اگر API بدهد)، Message ID، Sender Type
 *  - پیام گروه:   Group ID (chat_id)، عنوان (اگر موجود)، Sender ID،
 *    متن، Message ID
 *  - پیام کانال:  Channel ID، عنوان/username (اگر موجود)،
 *    Sender ID (اگر API بدهد)، Message ID، نوع رویداد
 *  - پیام فورواردشده: چت گیرنده + مبدأ فوروارد (نوع، chat_id،
 *    sender_id اصلی، message_id اصلی) — «مبدأ فوروارد» هرگز با
 *    «چت گیرنده» یکی شمرده نمی‌شود.
 *  - رویداد BotJoined: بررسی نوع عضویت و صلاحیت‌ها؛ در صورت فعال بودن
 *    SendMessages، خوش‌آمدگویی فارسی در چت ارسال می‌شود (Chat ID /
 *    نوع چت / عنوان / ربات / دسترسی). در صورت غیرفعال بودن، گزارش
 *    صادقانه «نداده شد» — موفقیت‌نمودی دروغین نمی‌دهد.
 *  - رویداد BotRemoved / BotPermissionsChanged / رویدادهای نامشخص:
 *    گزارش به مالک و لاگ.
 *
 * دستورات: /start | /id | /chat | /forward + دریافت پیام‌های معمولی
 * در حالت تشخیصی.
 *
 * حریم خصوصی (طبق مأموریت):
 *  - کانال: فقط به مالک گزارش می‌شود.
 *  - گروه: اگر فرستنده مالک/مدیر باشد در خودِ گروه پاسخ می‌دهد؛
 *    وگرنه فقط به مالک گزارش می‌شود.
 *  - خصوصی: به خودِ فرستنده پاسخ می‌دهد (ماهیت ربات GetChatID)؛
 *    اگر فرستنده مالک/مدیر نباشد، فقط به مالک گزارش می‌شود.
 *
 * امنیت:
 *  - token هرگز لاگ/ارسال نمی‌شود (TokenHelper::redactTokens روی هر
 *    خطای احتمالی).
 *  - dedup اتمیک با Cache::add (TTL یک ساعت) — پردازش تکراری آپدیت
 *    تکراری (و در نتیجه پیام خوش‌آمد تکراری) اتفاق نمی‌افتد.
 *  - لاگ به جدول bot_logs با خوانش دفاعی: شناسه‌های روبیکا رشته‌ای
 *    و opaque هستند و ستون‌های bigint این جدول نمی‌توانند آن‌ها را
 *    نگهداری کنند؛ فقط مقدار عددیِ امن cast می‌شود و غیرعدد → 0/null.
 *
 * ⚠️ محدودیت‌های BLOCKED تا تست زنده (مستندات محلی تأیید نکرده‌اند):
 *  - ساختار دقیق رویدادهای BotJoined/BotRemoved/BotPermissionsChanged
 *  - فیلدهای مدل Chat خروجی getChat
 *  - فیلدهای مبدأ فوروارد
 * همه با RubikaUpdateNormalizer به‌صورت دفاعی خوانده می‌شوند؛ فیلد
 * ناموجود «—» می‌شود و هیچ حدسی زده نمی‌شود.
 */
class RubikaDiagnosticBotController extends Controller
{
    /**
     * مقدار ستون bot_logs.webhook_endpoint_uri (varchar(30)).
     */
    private const ENDPOINT_URI = 'webhook-rubika-diagnostic';

    /**
     * مدت ماندگاری کلید dedup (ثانیه).
     */
    private const DEDUP_TTL = 3600;

    private const START_MENU = "🩺 ربات تشخیصی روبیکا

این ربات برای تحلیل فنی پیام‌ها و رویدادها در روبیکا ساخته شده است.

دستورات:
/id — نمایش شناسه‌های چت و فرستنده
/chat — اطلاعات کامل چت (getChat)
/forward — اطلاعات پیام فورواردشده
/start — نمایش این منو

هر پیام معمولی (متن) هم به‌صورت خودکار گزارش تشخیصی می‌گیرد.";

    /**
     * نقطهٔ ورود webhook ربات تشخیصی روبیکا.
     *
     * همیشه 200 + {"ok":true} برمی‌گرداند تا روبیکا آپدیت را
     * «consumed» بشمارد و دوباره ارسال نکند.
     */
    public function webhook(BotRequest $request): JsonResponse
    {
        try {
            $this->handle($request);
        } catch (\Throwable $e) {
            Log::error('Rubika diagnostic webhook error', [
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // هستهٔ پردازش
    // ------------------------------------------------------------------

    private function handle(BotRequest $request): void
    {
        $payload = $request->json()->all();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        if (!RubikaUpdateNormalizer::isRubikaUpdate($payload)) {
            // نه آپدیت روبیکا بود؛ فقط تأیید دریافت (ack).
            return;
        }

        $normalizer = new RubikaUpdateNormalizer($payload);

        $bot = $this->resolveBot($request);
        if ($bot === null || !$this->hasUsableToken($bot)) {
            Log::warning('Rubika diagnostic: bot not found for token', [
                'token_sha1' => sha1((string) $request->input('token')),
            ]);

            return;
        }

        // dedup اتمیک — اگر همین آپدیت قبلاً پردازش شده، نادیده بگیر.
        $dedupKey = $this->dedupKeyFor($normalizer);
        if (!Cache::add($dedupKey, 1, self::DEDUP_TTL)) {
            Log::info('Rubika diagnostic: duplicate update ignored', [
                'key' => $dedupKey,
            ]);

            return;
        }

        $this->logUpdate($request, $normalizer, $bot);

        $client = new RubikaBotClient($bot->rubika_bot_token);

        if ($normalizer->isMessage()) {
            $this->handleMessage($normalizer, $client, $bot);
        } elseif ($normalizer->isChatEvent()) {
            $this->handleChatEvent($normalizer, $client, $bot);
        }
    }

    /**
     * یافتن ربات از token (ستون rubika_bot_token) — با فیلبک روی
     * پارامتر bot_id در URL webhook.
     */
    private function resolveBot(BotRequest $request): ?Bot
    {
        $token = (string) $request->input('token');

        $bot = Bot::where(SyncPlatformRegistry::tokenColumn('rubika'), $token)->first();

        if ($bot === null && $request->input('bot_id')) {
            $bot = Bot::find($request->input('bot_id'));
        }

        return $bot;
    }

    private function hasUsableToken(?Bot $bot): bool
    {
        return $bot !== null
            && is_string($bot->rubika_bot_token)
            && $bot->rubika_bot_token !== '';
    }

    /**
     * کلید dedup اتمیک:
     *  - پیام:    rubika_diag:msg:{chatId}:{messageId (یا md5 بدنه)}
     *  - رویداد:  rubika_diag:event:{type}:{chatId}
     *             (BotPermissionsChanged با md5 صلاحیت‌ها؛ تا تغییر
     *             واقعی دوباره پردازش شود و تکرار خیر نگردد)
     */
    private function dedupKeyFor(RubikaUpdateNormalizer $n): string
    {
        if ($n->isMessage()) {
            $suffix = $n->messageId()
                ?? substr(md5((string) json_encode($n->rawUpdate())), 0, 16);

            return 'rubika_diag:msg:' . ($n->chatId() ?? 'unknown') . ':' . $suffix;
        }

        $base = 'rubika_diag:event:' . $n->typeLower() . ':' . ($n->chatId() ?? 'unknown');

        if ($n->typeLower() === 'botpermissionschanged') {
            $perm = $this->extractEventPermissions($n);

            return $base . ':' . md5($perm['raw'] . '|' . implode(',', $perm['keys']));
        }

        return $base;
    }

    /**
     * لاگ دفاعی به جدول bot_logs.
     *
     * از LogHelper::log استفاده نمی‌کنیم: آن کمکی برای client SDK
     * (Telegram) ساخته شده و bot_logs.chat_id از نوع bigInteger NOT NULL
     * است در حالی که chat_id روبیکای رشته‌ای و opaque است. اینجا فقط
     * مقدار عددیِ امن cast می‌شود و غیرعدد → 0/null.
     */
    private function logUpdate(BotRequest $request, RubikaUpdateNormalizer $n, Bot $bot): void
    {
        try {
            $text = (string) ($n->text() ?? $n->type());

            $log = new BotLog();
            $log->webhook_endpoint_uri = self::ENDPOINT_URI;
            $log->bot_mother_id = (int) ($request->input('bot_mother_id') ?? 1);
            $log->language = $request->input('language');
            $log->locale = App::getLocale();
            $log->type = 'rubika';
            $log->text = mb_substr(TokenHelper::redactTokens($text), 0, 199);
            $log->is_command = str_starts_with($text, '/');
            // شناسه‌های چت روبیکا رشته‌ای‌اند؛ معادل عددی این ستون معنی ندارد.
            $log->channel_group_type = 0;
            $log->bot_id = $bot->id;
            $log->chat_id = $this->toBigInt($n->chatId()) ?? 0;
            $log->message_id = $this->toBigInt($n->messageId());
            $log->from_id = $this->toBigInt($n->senderId());
            // from_chat_id در روبیکا رشته‌ای است و ستون bigint است → null.
            $log->from_chat_id = null;
            $log->save();
        } catch (\Throwable $e) {
            Log::warning('Rubika diagnostic log failed', [
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);
        }

        Log::info('Rubika diagnostic update', [
            'type' => $n->type(),
            'chat_id' => $n->chatId(),
            'message_id' => $n->messageId(),
            'sender_id' => $n->senderId(),
            'sender_type' => $n->senderType(),
            'forwarded' => $n->isForwarded(),
        ]);
    }

    /**
     * cast امن به int 64-bit (حداکثر 18 رقم)؛ غیرعدد → null.
     */
    private function toBigInt(?string $value): ?int
    {
        if ($value === null || !preg_match('/^-?\d{1,18}$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    // ------------------------------------------------------------------
    // پیام‌ها (NewMessage / UpdatedMessage)
    // ------------------------------------------------------------------

    private function handleMessage(RubikaUpdateNormalizer $n, RubikaBotClient $client, Bot $bot): void
    {
        $text = trim((string) $n->text());
        $chatId = $n->chatId();
        $senderId = $n->senderId();
        $chatInfo = $this->detectChatInfo($n, $client);
        $chatKind = $this->normalizeChatType($chatInfo['raw_type']);
        $isOwner = $this->isOwnerOrAdmin($senderId, $bot);

        // دستورات
        if (preg_match('/^\/(start|id|chat|forward)\b/i', $text, $m)) {
            // دستورات فقط در چت خصوصی یا وقتی فرستنده مالک/مدیر است.
            if ($chatKind === 'private' || $isOwner) {
                $report = match (strtolower($m[1])) {
                    'start' => self::START_MENU,
                    'id' => $this->buildIdReport($n, $chatInfo),
                    'chat' => $this->buildChatReport($n, $client),
                    'forward' => $this->buildForwardReport($n),
                };
                $this->reply($client, $chatId, $report);
            }

            return;
        }

        // پیام معمولی — گزارش تشخیصی با مسیریابی حریم خصوصی
        $report = $this->buildMessageReport($n, $chatInfo);

        switch ($chatKind) {
            case 'channel':
                // کانال: ربات فقط می‌تواند به مالک گزارش بدهد.
                $this->notifyOwner($client, $bot, $report);
                break;

            case 'group':
                if ($isOwner) {
                    $this->reply($client, $chatId, $report);
                } else {
                    $this->notifyOwner($client, $bot, $report);
                }
                break;

            default:
                // private یا نوع نامشخص
                if ($isOwner) {
                    $extra = $chatKind === null
                        ? "\n⚠️ نوع چت مشخص نشد (فیلد نوع در آپدیت یافت نشد)"
                        : '';
                    $this->reply($client, $chatId, $report . $extra);
                } else {
                    $this->notifyOwner($client, $bot, $report);
                }
        }
    }

    /**
     * تشخیص دفاعی نوع چت و فیلدهایش:
     *  1) اشیای inline (update.chat / new_message.chat)
     *  2) فیلدهای chat_type در سطح update یا message
     *  3) getChat (اگر دو مورد بالا نداد)
     *
     * @return array{raw_type: ?string, fields: ?array}
     */
    private function detectChatInfo(RubikaUpdateNormalizer $n, RubikaBotClient $client): array
    {
        $u = $n->rawUpdate();
        $m = is_array($u['new_message'] ?? null) ? $u['new_message'] : [];

        $inline = null;
        foreach ([$u['chat'] ?? null, $m['chat'] ?? null] as $candidate) {
            if (is_array($candidate)) {
                $inline = $candidate;
                break;
            }
        }

        $fields = $inline !== null
            ? RubikaUpdateNormalizer::chatFields($inline)
            : null;
        $rawType = $fields['type'] ?? null;

        if ($rawType === null) {
            $rawType = $this->nonEmptyString($u['chat_type'] ?? null)
                ?? $this->nonEmptyString($m['chat_type'] ?? null);
        }

        if (($fields === null || $rawType === null) && $n->chatId() !== null) {
            $chat = $this->fetchChatFields($client, $n->chatId());
            if ($chat !== null) {
                $fromGetChat = RubikaUpdateNormalizer::chatFields($chat);
                $fields = $fields ?? $fromGetChat;
                $rawType = $rawType ?? $fromGetChat['type'];
                // ترکیب: فیلدهای موجود از هر دو منبع
                if ($fields !== null) {
                    foreach ($fromGetChat as $key => $value) {
                        if ($fields[$key] === null && $value !== null) {
                            $fields[$key] = $value;
                        }
                    }
                }
            }
        }

        return ['raw_type' => $rawType, 'fields' => $fields];
    }

    /**
     * getChat دفاعی — هر خطا فقط لاگ می‌شود (BLOCKED تا تست زنده).
     */
    private function fetchChatFields(?RubikaBotClient $client, ?string $chatId): ?array
    {
        if ($client === null || $chatId === null) {
            return null;
        }

        try {
            $response = $client->getChat($chatId);

            return RubikaUpdateNormalizer::extractChatFromGetChatResponse($response);
        } catch (\Throwable $e) {
            Log::warning('Rubika diagnostic getChat failed', [
                'chat_id' => $chatId,
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * نرمال‌سازی نوع چت به سه دستهٔ رفتاری + private.
     */
    private function normalizeChatType(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return match (strtolower(trim($raw))) {
            'user', 'private' => 'private',
            'bot' => 'private',
            'group', 'supergroup' => 'group',
            'channel' => 'channel',
            default => null,
        };
    }

    // ------------------------------------------------------------------
    // سازنده‌های گزارش
    // ------------------------------------------------------------------

    private function buildMessageReport(RubikaUpdateNormalizer $n, array $chatInfo): string
    {
        $u = $n->rawUpdate();
        $m = is_array($u['new_message'] ?? null) ? $u['new_message'] : [];
        $fields = $chatInfo['fields'] ?? [];
        $chatKind = $this->normalizeChatType($chatInfo['raw_type']);

        $lines = [
            '🩺 گزارش تشخیصی روبیکا',
            '─────────────',
            'Chat ID: ' . ($n->chatId() ?? '—'),
            'نوع چت: ' . RubikaUpdateNormalizer::chatTypeLabel($chatInfo['raw_type']),
            'Sender ID: ' . ($n->senderId() ?? '—'),
            'Sender Type: ' . ($n->senderType() ?? '—'),
            'Message ID: ' . ($n->messageId() ?? '—'),
            'Time: ' . ($n->time() ?? '—'),
        ];

        if ($chatKind === 'private') {
            $fn = $this->nonEmptyString($m['first_name'] ?? null);
            $ln = $this->nonEmptyString($m['last_name'] ?? null);
            if ($fn !== null || $ln !== null) {
                $lines[] = 'نام: ' . ($fn ?? '—') . ($fn !== null && $ln !== null ? ' ' . $ln : '');
            } else {
                $lines[] = 'نام: — (فیلد نام در پاسخ API یافت نشد)';
            }
        }

        if (($fields['username'] ?? null) !== null) {
            $lines[] = 'Username: ' . $fields['username'];
        }
        if (($fields['title'] ?? null) !== null && $chatKind !== 'private') {
            $lines[] = 'عنوان چت: ' . $fields['title'];
        }
        if (($fields['member_count'] ?? null) !== null) {
            $lines[] = 'تعداد اعضا: ' . $fields['member_count'];
        }

        $text = $n->text() ?? $n->caption();
        if ($text !== null && $text !== '') {
            $lines[] = 'متن: ' . mb_substr($text, 0, 300);
        }

        if ($n->isForwarded()) {
            $f = $n->forwardedInfo();
            $lines[] = '─────────────';
            $lines[] = '↪️ این پیام فورواردشده است — ⚠️ مبدأ فوروارد ≠ چت گیرنده';
            $lines[] = 'نوع مبدأ: ' . ($f['source_type'] ?? '—');
            $lines[] = 'Chat ID مبدأ: ' . ($f['from_chat_id'] ?? '—');
            $lines[] = 'Sender ID اصلی: ' . ($f['from_sender_id'] ?? '—');
            $lines[] = 'Message ID اصلی: ' . ($f['original_message_id'] ?? '—');
        }

        return implode("\n", $lines);
    }

    private function buildIdReport(RubikaUpdateNormalizer $n, array $chatInfo): string
    {
        return implode("\n", [
            '🆔 شناسه‌ها',
            '─────────────',
            'Chat ID: ' . ($n->chatId() ?? '—'),
            'نوع چت: ' . RubikaUpdateNormalizer::chatTypeLabel($chatInfo['raw_type']),
            'Sender ID: ' . ($n->senderId() ?? '—'),
            'Sender Type: ' . ($n->senderType() ?? '—'),
            'Message ID: ' . ($n->messageId() ?? '—'),
        ]);
    }

    private function buildChatReport(RubikaUpdateNormalizer $n, ?RubikaBotClient $client): string
    {
        $chat = $this->fetchChatFields($client, $n->chatId());

        if ($chat === null) {
            return implode("\n", [
                '💬 اطلاعات چت',
                '─────────────',
                'Chat ID: ' . ($n->chatId() ?? '—'),
                '⚠️ getChat در دسترس نبود یا پاسخ قابل‌خوانش نبود؛',
                'فقط Chat ID بالا از بدنهٔ آپدیت معتبر است.',
            ]);
        }

        $fields = RubikaUpdateNormalizer::chatFields($chat);

        return implode("\n", [
            '💬 اطلاعات چت (getChat)',
            '─────────────',
            'Chat ID: ' . ($fields['id'] ?? $n->chatId() ?? '—'),
            'نوع چت: ' . RubikaUpdateNormalizer::chatTypeLabel($fields['type']),
            'عنوان: ' . ($fields['title'] ?? '—'),
            'Username: ' . ($fields['username'] ?? '—'),
            'تعداد اعضا: ' . ($fields['member_count'] ?? '—'),
            'توضیحات: ' . ($fields['description'] ?? '—'),
        ]);
    }

    private function buildForwardReport(RubikaUpdateNormalizer $n): string
    {
        if (!$n->isForwarded()) {
            return implode("\n", [
                '↪️ اطلاعات فوروارد',
                '─────────────',
                'این پیام فورواردشده نیست',
                '(فیلد مبدأ فوروارد در بدنهٔ آپدیت یافت نشد).',
            ]);
        }

        $f = $n->forwardedInfo();

        return implode("\n", [
            '↪️ اطلاعات فورواردشدهٔ این پیام',
            '─────────────',
            '⚠️ مبدأ فوروارد ≠ چت گیرنده',
            'Chat ID گیرنده: ' . ($n->chatId() ?? '—'),
            'نوع مبدأ: ' . ($f['source_type'] ?? '—'),
            'Chat ID مبدأ: ' . ($f['from_chat_id'] ?? '—'),
            'Sender ID اصلی: ' . ($f['from_sender_id'] ?? '—'),
            'Message ID اصلی: ' . ($f['original_message_id'] ?? '—'),
            'Message ID این پیام: ' . ($n->messageId() ?? '—'),
        ]);
    }

    // ------------------------------------------------------------------
    // رویدادهای چت (BotJoined / BotRemoved / BotPermissionsChanged / ...)
    // ------------------------------------------------------------------

    private function handleChatEvent(RubikaUpdateNormalizer $n, RubikaBotClient $client, Bot $bot): void
    {
        match (true) {
            $n->typeLower() === 'botjoined' => $this->handleBotJoined($n, $client, $bot),
            $n->typeLower() === 'botremoved' => $this->handleBotRemoved($n, $client, $bot),
            $n->typeLower() === 'botpermissionschanged' => $this->handlePermissionsChanged($n, $client, $bot),
            default => $this->notifyOwner($client, $bot, $this->buildUnknownEventReport($n)),
        };
    }

    /**
     * BotJoined — بررسی نوع عضویت و صلاحیت‌ها؛ در صورت امکان، ارسال
     * خوش‌آمدگویی فارسی با Chat ID / نوع چت / عنوان / ربات / دسترسی.
     *
     * رفتار صادقانه:
     *  - has_send_messages === false → اصلا تلاش نمی‌کند و دلیل را
     *    گزارش می‌کند.
     *  - null (نامشخص) → تلاش می‌کند و نتیجه را با analyzeResponse
     *    راستی‌آزمایی می‌کند؛ در صورت شکست، کلید dedup را پاک می‌کند
     *    تا در رویداد بعدی دوباره امتحان شود.
     */
    private function handleBotJoined(RubikaUpdateNormalizer $n, RubikaBotClient $client, Bot $bot): void
    {
        $chatId = $n->chatId();
        $chat = $this->fetchChatFields($client, $chatId);
        $chatFields = RubikaUpdateNormalizer::chatFields($chat);
        $perm = $this->extractEventPermissions($n);

        if ($perm['has_send_messages'] === false) {
            $this->notifyOwner($client, $bot, implode("\n", [
                '🚫 BotJoined — ربات به چت پیوست اما SendMessages غیرفعال است',
                '─────────────',
                'Chat ID: ' . ($chatId ?? '—'),
                'نوع چت: ' . RubikaUpdateNormalizer::chatTypeLabel($chatFields['type']),
                'صلاحیت‌ها: ' . ($perm['raw'] !== '' ? $perm['raw'] : '—'),
                'خوش‌آمدگویی ارسال نشد (دلیل: SendMessages غیرفعال).',
            ]));

            return;
        }

        $access = $perm['has_send_messages'] === true
            ? 'SendMessages فعال'
            : 'SendMessages قابل‌تشخیص نبود (فرض: فعال)';

        $botName = $bot->rubika_bot_name !== null && $bot->rubika_bot_name !== ''
            ? '@' . ltrim((string) $bot->rubika_bot_name, '@')
            : '—';

        $welcome = implode("\n", [
            '📢 ربات به این چت پیوست',
            '─────────────',
            'Chat ID: ' . ($chatId ?? '—'),
            'نوع چت: ' . RubikaUpdateNormalizer::chatTypeLabel($chatFields['type']),
            'عنوان چت: ' . ($chatFields['title'] ?? '—'),
            'ربات: ' . $botName,
            'دسترسی: ' . $access,
        ]);

        $ok = false;
        $detail = '—';
        try {
            $response = $client->sendMessage($chatId, $welcome);
            $analysis = RubikaBotClient::analyzeResponse($response);
            $ok = in_array($analysis['state'], [
                RubikaBotClient::STATE_SUCCESS_WITH_MESSAGE_ID,
                RubikaBotClient::STATE_SUCCESS_WITHOUT_MESSAGE_ID,
            ], true);
            $detail = $analysis['state'] . ($analysis['reason'] ? ' — ' . $analysis['reason'] : '');
        } catch (\Throwable $e) {
            $analysis = RubikaBotClient::classifyTransportException($e);
            $detail = $analysis['state'] . ($analysis['reason'] ? ' — ' . $analysis['reason'] : '');
        }

        if ($ok) {
            Log::info('Rubika diagnostic: BotJoined welcome sent', [
                'chat_id' => $chatId,
                'state' => $detail,
            ]);
            $this->notifyOwner($client, $bot, '✅ BotJoined — خوش‌آمدگویی در چت ارسال شد'
                . ($chatId ? " (Chat ID: $chatId)" : ''));

            return;
        }

        // شکست صادقانه — dedup پاک شود تا در رویداد بعدی دوباره امتحان شود.
        Cache::forget($this->dedupKeyFor($n));
        $this->notifyOwner($client, $bot, implode("\n", [
            '⚠️ BotJoined — ارسال خوش‌آمدگویی تأیید نشد',
            '─────────────',
            'Chat ID: ' . ($chatId ?? '—'),
            'وضعیت API: ' . $detail,
            'صلاحیت‌ها: ' . ($perm['raw'] !== '' ? $perm['raw'] : '—'),
            'کلید dedup پاک شد تا در رویداد بعدی دوباره امتحان شود.',
        ]));
    }

    private function handleBotRemoved(RubikaUpdateNormalizer $n, RubikaBotClient $client, Bot $bot): void
    {
        $chatId = $n->chatId();
        // اگر دوباره join شد، دوباره خوش‌آمد بگوید.
        Cache::forget('rubika_diag:event:botjoined:' . ($chatId ?? 'unknown'));

        $this->notifyOwner($client, $bot, implode("\n", [
            '🚪 BotRemoved — ربات از چت حذف شد',
            'Chat ID: ' . ($chatId ?? '—'),
            'کلید dedup رویداد BotJoined پاک شد تا در صورت بازگشت،',
            'دوباره خوش‌آمد گفته شود.',
        ]));
    }

    private function handlePermissionsChanged(RubikaUpdateNormalizer $n, RubikaBotClient $client, Bot $bot): void
    {
        $perm = $this->extractEventPermissions($n);

        $this->notifyOwner($client, $bot, implode("\n", [
            '🔧 BotPermissionsChanged — تغییر صلاحیت ربات در چت',
            '─────────────',
            'Chat ID: ' . ($n->chatId() ?? '—'),
            'کلیدهای صلاحیت: ' . (count($perm['keys']) ? implode(', ', $perm['keys']) : '—'),
            'SendMessages: ' . ($perm['has_send_messages'] === true
                ? 'فعال'
                : ($perm['has_send_messages'] === false ? 'غیرفعال' : 'قابل‌تشخیص نبود')),
            'Admin: ' . ($perm['is_admin'] === true
                ? 'بله'
                : ($perm['is_admin'] === false ? 'خیر' : '—')),
            'خام: ' . ($perm['raw'] !== '' ? $perm['raw'] : '—'),
        ]));
    }

    private function buildUnknownEventReport(RubikaUpdateNormalizer $n): string
    {
        $raw = TokenHelper::redactTokens((string) json_encode($n->rawUpdate(), JSON_UNESCAPED_UNICODE));

        return implode("\n", [
            '❓ رویداد ناشناخته از روبیکا',
            '─────────────',
            'نوع رویداد: ' . $n->type(),
            'Chat ID: ' . ($n->chatId() ?? '—'),
            'بدنه (بسته‌شده، حداکثر 500 کاراکتر): ' . mb_substr($raw, 0, 500),
            'این رویداد در مستندات محلی تأیید نشده است (BLOCKED تا تست زنده).',
        ]);
    }

    /**
     * خوانش دفاعی صلاحیت‌ها از بدنهٔ رویداد — بدنه ممکن است در
     * update مستقیم باشد یا در new_data/data/event/chat.
     */
    private function extractEventPermissions(RubikaUpdateNormalizer $n): array
    {
        $raw = $n->rawUpdate();
        $candidates = [$raw];

        foreach (['new_data', 'data', 'event', 'chat'] as $key) {
            if (is_array($raw[$key] ?? null)) {
                $candidates[] = $raw[$key];
            }
        }

        foreach ($candidates as $candidate) {
            $perm = RubikaUpdateNormalizer::extractPermissions($candidate);
            if ($perm['permissions'] !== null) {
                return $perm;
            }
        }

        return RubikaUpdateNormalizer::extractPermissions($raw);
    }

    // ------------------------------------------------------------------
    // ارسال
    // ------------------------------------------------------------------

    /**
     * پاسخ به همان چت دریافتی. نتیجه با analyzeResponse راستی‌آزمایی
     * می‌شود (موفقیت‌نمودی دروغین).
     */
    private function reply(?RubikaBotClient $client, ?string $chatId, string $text): bool
    {
        if ($client === null || $chatId === null) {
            Log::warning('Rubika diagnostic: cannot reply (no client or chat id)');

            return false;
        }

        try {
            $response = $client->sendMessage($chatId, $text);
            $analysis = RubikaBotClient::analyzeResponse($response);
            $ok = in_array($analysis['state'], [
                RubikaBotClient::STATE_SUCCESS_WITH_MESSAGE_ID,
                RubikaBotClient::STATE_SUCCESS_WITHOUT_MESSAGE_ID,
            ], true);

            if (!$ok) {
                Log::warning('Rubika diagnostic reply not confirmed', [
                    'chat_id' => $chatId,
                    'state' => $analysis['state'],
                    'reason' => $analysis['reason'],
                ]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::warning('Rubika diagnostic reply failed', [
                'chat_id' => $chatId,
                'error' => TokenHelper::redactTokens($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * گزارش به مالک ربات (rubika_owner_chat_id) + مدیران سیستم
     * (AdminHelper). هر گیرنده جدا و در try/catch خودش.
     */
    private function notifyOwner(?RubikaBotClient $client, ?Bot $bot, string $text): void
    {
        if ($client === null) {
            Log::warning('Rubika diagnostic: cannot notify owner (no client)');

            return;
        }

        $ids = [];
        if ($bot !== null && $bot->rubika_owner_chat_id !== null) {
            $ids[] = (string) $bot->rubika_owner_chat_id;
        }
        foreach (AdminHelper::getAdmins() as $admin) {
            $ids[] = (string) $admin;
        }

        foreach (array_unique(array_filter($ids, static fn ($id) => $id !== '' && $id !== '0')) as $id) {
            try {
                $response = $client->sendMessage($id, $text);
                $analysis = RubikaBotClient::analyzeResponse($response);
                if (!in_array($analysis['state'], [
                    RubikaBotClient::STATE_SUCCESS_WITH_MESSAGE_ID,
                    RubikaBotClient::STATE_SUCCESS_WITHOUT_MESSAGE_ID,
                ], true)) {
                    Log::warning('Rubika diagnostic owner notify not confirmed', [
                        'to' => $id,
                        'state' => $analysis['state'],
                        'reason' => $analysis['reason'],
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Rubika diagnostic owner notify failed', [
                    'to' => $id,
                    'error' => TokenHelper::redactTokens($e->getMessage()),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------

    private function isOwnerOrAdmin(?string $senderId, ?Bot $bot): bool
    {
        if ($senderId === null) {
            return false;
        }

        if ($bot !== null
            && $bot->rubika_owner_chat_id !== null
            && (string) $bot->rubika_owner_chat_id === $senderId) {
            return true;
        }

        return AdminHelper::isAdmin($senderId);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
