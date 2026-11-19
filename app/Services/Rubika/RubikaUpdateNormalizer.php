<?php

namespace App\Services\Rubika;

/**
 * نرمال‌ساز دفاعی آپدیت‌های ربات روبیکا (BM-RUBIKA-DIAGNOSTIC-BOT).
 *
 * شکل پایهٔ مستندشدهٔ آپدیت (docs/features/rubika/sources/rubika_readme.md):
 *
 *   { "update": {
 *       "type": "NewMessage",          // NewMessage|UpdatedMessage|RemovedMessage|StartedBot|StoppedBot|EventData|...
 *       "chat_id": "b0QFtn0C...",      // شناسهٔ چت (رشته‌ای؛ برای ارسال بعدی همین مقدار استفاده می‌شود)
 *       "new_message": { Message }     // Message: message_id, text, time, is_edited, sender_type, sender_id, aux_data, ...
 *   } }
 *
 * ⚠️ موارد دفاعی (در مستندات محلی اسقاط/تأیید نشده‌اند — BLOCKED تا تست زنده):
 *
 *  1) رویدادهای BotJoined / BotRemoved / BotPermissionsChanged: در مستندات
 *     رسمی محلی اسقاط نشده‌اند؛ کاربر ادعا کرده وجود دارند. هر update با
 *     type نامشخص به‌عنوان «رویداد چت» عبور می‌کند (ignore نمی‌شود) و
 *     فیلدهایش به‌صورت دفاعی خوانده می‌شوند — ساختار جدید فرض برقرار
 *     نمی‌شود و در خروجی با برچسب «نامشخص» گزارش می‌شود.
 *
 *  2) فیلدهای پیام فورواردشده: مستندات رسمی (اسکرین‌شوت کاربر) فیلدهای
 *     `type_from`, `from_chat_id`, `from_sender_id`, `message_id` را برای
 *     اطلاعات مبدأ فوروارد اعلام کرده است. در اسقاط محلی هیچ نمونه‌ای
 *     از فیلد `forwarded_from` یا این چهار فیلد دیده نشده؛ هر دو مکانیزم
 *     به‌صورت دفاعی خوانده می‌شوند:
 *       - `new_message.forwarded_from` (اگر روزی در آپدیت ظاهر شود)
 *       - فیلدهای مستقیم `type_from` / `from_chat_id` / `from_sender_id`
 *         در سطح Message یا سطح Update.
 *     اصل مهم: «مبدأ فوروارد» هرگز با «چت گیرنده» یکی شمرده نمی‌شود؛
 *     اگر فیلد مبدأ موجود نباشد، به‌جای حدس، «—» گزارش می‌شود.
 *
 *  3) مدل Chat (خروجی getChat): فیلدهایش محلی تأیید نشده — خوانش فقط از
 *     میان نام‌های محتمل شناخته‌شده (id/type/title/username/member_count/...)
 *     و هر فیلد ناموجود «—» می‌شود.
 *
 * همهٔ مقادیر «صاف» (بدون حدس) برگردانده می‌شوند؛ تصمیم نمایش/ارسال با
 * مصرف‌کننده (RubikaDiagnosticBotController) است.
 */
final class RubikaUpdateNormalizer
{
    /**
     * نام‌های شناخته‌شدهٔ فیلد نوع چت — از مدل Chat (مستندات: User/Bot/Group/Channel).
     * هر نامی که «چت»/«نوع» در آن باشد هم به‌صورت دفاعی بررسی می‌شود.
     */
    private const CHAT_TYPE_KEYS = ['type', 'chat_type', 'kind', 'category'];

    /**
     * نام‌های محتمل فیلد «نوع/صلاحیت» در رویداد BotPermissionsChanged.
     */
    private const PERMISSION_KEYS = ['permissions', 'permission', 'perms', 'admin_permissions', 'bot_permissions', 'rights'];

    public function __construct(private readonly array $payload)
    {
    }

    /**
     * آیا بدنهٔ ورودی اصلاً یک آپدیت روبیکا است؟
     *
     * قبول می‌شود:
     *  - { "update": { "type": ..., ... } }      (شکل مستند)
     *  - { "type": ..., ... }                    (آپدیتِ برهنه)
     */
    public static function isRubikaUpdate(array $payload): bool
    {
        $update = $payload['update'] ?? $payload;

        return is_array($update) && is_string($update['type'] ?? null) && $update['type'] !== '';
    }

    public function type(): string
    {
        $t = $this->update()['type'] ?? '';

        return is_string($t) ? trim($t) : '';
    }

    /**
     * آرایهٔ خام update (سطح update یا بدنه) — فقط برای خوانش دفاعی توسط
     * مصرف‌کننده (مثلاً تشخیص نوع چت یا خوانش صلاحیت‌ها).
     */
    public function rawUpdate(): array
    {
        return $this->update();
    }

    public function typeLower(): string
    {
        return strtolower($this->type());
    }

    /**
     * شناسهٔ چت گیرنده (update.chat_id → new_message.chat_id).
     * همیشه همان مقدار باید برای ارسال‌های بعدی استفاده شود (مستندات).
     */
    public function chatId(): ?string
    {
        $u = $this->update();
        $id = $u['chat_id'] ?? ($u['new_message']['chat_id'] ?? null);

        return $this->nonEmptyString($id);
    }

    /**
     * شناسهٔ پیام (new_message.message_id).
     */
    public function messageId(): ?string
    {
        return $this->nonEmptyString($this->message()['message_id'] ?? null);
    }

    /**
     * شناسهٔ فرستندهٔ پیام (new_message.sender_id).
     */
    public function senderId(): ?string
    {
        return $this->nonEmptyString($this->message()['sender_id'] ?? null);
    }

    /**
     * نوع فرستنده بر اساس مستندات: 'User' | 'Bot' | null.
     * (محدودیت API روبیکا: «نوع کاربر» فقط User/Bot است؛ نوع چت User/Bot/
     * Group/Channel از getChat یا نوع چت دریافتی مشخص می‌شود.)
     */
    public function senderType(): ?string
    {
        return $this->nonEmptyString($this->message()['sender_type'] ?? null);
    }

    public function text(): ?string
    {
        $t = $this->message()['text'] ?? null;

        return is_string($t) ? $t : null;
    }

    public function caption(): ?string
    {
        $t = $this->message()['caption'] ?? null;

        return is_string($t) ? $t : null;
    }

    /**
     * شناسهٔ پیام پاسخ‌داده‌شده (reply_to_message_id) — دفاعی.
     */
    public function replyToMessageId(): ?string
    {
        $m = $this->message();
        $u = $this->update();

        return $this->nonEmptyString(
            $m['reply_to_message_id'] ?? ($u['reply_to_message_id'] ?? null)
        );
    }

    public function time(): ?string
    {
        return $this->nonEmptyString($this->message()['time'] ?? null);
    }

    /**
     * آیا این update یک پیام (جدید/ویرایش‌شده) است؟
     */
    public function isMessage(): bool
    {
        return in_array($this->typeLower(), ['newmessage', 'updatedmessage'], true);
    }

    /**
     * آیا این update یک رویداد چت (BotJoined/BotRemoved/BotPermissionsChanged/...) است؟
     *
     * دفاعی: هر type نامشخصی «رویداد چت» شمرده می‌شود تا نادیده گرفته نشود
     * (مستندات محلی این رویدادها را تأیید نکرده‌اند؛ باید دیده شوند).
     */
    public function isChatEvent(): bool
    {
        return !$this->isMessage() && $this->chatId() !== null;
    }

    // ------------------------------------------------------------------
    // فوروارد — مبدأ فوروارد ≠ چت گیرنده
    // ------------------------------------------------------------------

    /**
     * آیا این پیام فورواردشده است؟
     *
     * معیار: وجود هر یک از مکانیزم‌های شناخته‌شده:
     *  - new_message.forwarded_from (آرایه)
     *  - فیلدهای مستقیم type_from / from_chat_id / from_sender_id (در Message یا Update)
     */
    public function isForwarded(): bool
    {
        $m = $this->message();
        $u = $this->update();

        if (is_array($m['forwarded_from'] ?? null)) {
            return true;
        }

        foreach (['type_from', 'from_chat_id', 'from_sender_id'] as $key) {
            if ($this->nonEmptyString($m[$key] ?? null) !== null
                || $this->nonEmptyString($u[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * اطلاعات مبدأ پیام فورواردشده.
     *
     * @return array{
     *     source_type: ?string,      // User|Channel|Bot|... (مقدار type_from یا forwarded_from.type_from)
     *     from_chat_id: ?string,     // chat_id چت مبدأ (from_chat_id یا forwarded_from.from_chat_id/chat_id)
     *     from_sender_id: ?string,   // sender_id فرستندهٔ اصلی (from_sender_id یا forwarded_from.sender_id/from)
     *     original_message_id: ?string, // message_id پیام اصلی (from_message_id یا forwarded_from.message_id)
     *     source: string             // 'none' | 'forwarded_from' | 'direct_fields'
     * }
     *
     * فقط مقادیر واقعاً موجود در بدنه برگردانده می‌شود؛ هیچ حدسی زده نمی‌شود.
     */
    public function forwardedInfo(): array
    {
        $m = $this->message();
        $u = $this->update();

        $source = 'none';
        $sourceType = null;
        $fromChatId = null;
        $fromSenderId = null;
        $originalMessageId = null;

        // مکانیزم 1: آرایهٔ forwarded_from (نام مستند نشده؛ دفاعی)
        $ff = $m['forwarded_from'] ?? ($u['forwarded_from'] ?? null);
        if (is_array($ff)) {
            $source = 'forwarded_from';
            $sourceType = $this->nonEmptyString($ff['type_from'] ?? ($ff['type'] ?? ($ff['from_type'] ?? null)));
            $fromChatId = $this->nonEmptyString($ff['from_chat_id'] ?? ($ff['chat_id'] ?? null));
            $fromSenderId = $this->nonEmptyString($ff['from_sender_id'] ?? ($ff['sender_id'] ?? ($ff['from'] ?? null)));
            $originalMessageId = $this->nonEmptyString($ff['from_message_id'] ?? ($ff['message_id'] ?? null));
        }

        // مکانیزم 2: فیلدهای مستقیم (اسکرین‌شوت مستندات رسمی: type_from/from_chat_id/from_sender_id)
        //         — فقط جایی که مکانیزم 1 مقدار ندهد تکمیل می‌شود.
        $direct = function (?string $current, string $key) use ($m, $u): ?string {
            if ($current !== null) {
                return $current;
            }

            return $this->nonEmptyString($m[$key] ?? ($u[$key] ?? null));
        };

        if ($sourceType === null || $fromChatId === null || $fromSenderId === null || $originalMessageId === null) {
            $sourceType = $direct($sourceType, 'type_from');
            $fromChatId = $direct($fromChatId, 'from_chat_id');
            $fromSenderId = $direct($fromSenderId, 'from_sender_id');
            $originalMessageId = $direct($originalMessageId, 'from_message_id');
            // فقط اگر واقعا مقداری از مکانیزم مستقیم آمده باشد
            if ($source === 'none'
                && ($sourceType !== null || $fromChatId !== null || $fromSenderId !== null || $originalMessageId !== null)) {
                $source = 'direct_fields';
            }
        }

        return [
            'source_type' => $sourceType,
            'from_chat_id' => $fromChatId,
            'from_sender_id' => $fromSenderId,
            'original_message_id' => $originalMessageId,
            'source' => $source,
        ];
    }

    // ------------------------------------------------------------------
    // getChat — خوانش دفاعی از مدل Chat (فیلدها محلی تأیید نشده)
    // ------------------------------------------------------------------

    /**
     * استخراج «اشیاء چت» از پاسخ getChat:
     *  - پاسخ مستقیم { "chat": {...} }
     *  - پاکت دو سطحی { "data": { "chat": {...} } } / { "data": {...فیلدهای خودِ چت...} }
     *
     * @return array|null آرایهٔ فیلدهای چت یا null اگر هیچ چیزی پیدا نشد
     */
    public static function extractChatFromGetChatResponse(array $response): ?array
    {
        $candidates = [];

        if (is_array($response['chat'] ?? null)) {
            $candidates[] = $response['chat'];
        }

        if (is_array($response['data'] ?? null)) {
            if (is_array($response['data']['chat'] ?? null)) {
                $candidates[] = $response['data']['chat'];
            }
            // data خودش ممکن است مستقیم اشیای چت باشد
            $candidates[] = $response['data'];
        }

        foreach ($candidates as $candidate) {
            if (self::looksLikeChat($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * آیا این آرایه شبیه مدل Chat است؟ (حداقل یکی از کلیدهای مشخصه)
     */
    private static function looksLikeChat(array $chat): bool
    {
        $keys = array_map('strtolower', array_keys($chat));

        foreach (['id', 'type', 'title', 'name', 'username', 'member_count', 'members_count'] as $known) {
            if (in_array($known, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * خوانش دفاعی فیلدهای چت — فقط نام‌های محتمل شناخته‌شده؛
     * فیلد ناموجود => '—' (برچسب نبود؛ هرگز حدس).
     */
    public static function chatFields(?array $chat): array
    {
        $get = function (array $keys) use ($chat): ?string {
            if ($chat === null) {
                return null;
            }
            foreach ($keys as $key) {
                $value = $chat[$key] ?? null;
                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
            }

            return null;
        };

        $rawType = null;
        if ($chat !== null) {
            foreach (self::CHAT_TYPE_KEYS as $key) {
                if (isset($chat[$key]) && is_scalar($chat[$key])) {
                    $rawType = (string) $chat[$key];
                    break;
                }
            }
            // دفاع: هر کلیدی که 'type' درش باشد و مقدار اسکالر/رشته‌ای داشته باشد
            if ($rawType === null) {
                foreach ($chat as $key => $value) {
                    if (is_string($key) && str_contains(strtolower($key), 'type')
                        && is_string($value) && $value !== '') {
                        $rawType = $value;
                        break;
                    }
                }
            }
        }

        return [
            'id' => $get(['id', 'chat_id']),
            'type' => $rawType,
            'title' => $get(['title', 'name', 'chat_title', 'bot_title']),
            'username' => $get(['username']),
            'member_count' => $get(['member_count', 'members_count', 'members', 'participant_count']),
            'description' => $get(['description', 'bio']),
        ];
    }

    /**
     * برچسب فارسی خوانا برای نوع چت (بر اساس مستندات: User/Bot/Group/Channel).
     */
    public static function chatTypeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '—';
        }

        return match (strtolower($type)) {
            'user' => 'User (خصوصی کاربر)',
            'bot' => 'Bot (خصوصی ربات)',
            'group' => 'Group (گروه)',
            'channel' => 'Channel (کانال)',
            default => $type,
        };
    }

    /**
     * خوانش دفاعی صلاحیت‌های ربات در چت (رویداد BotPermissionsChanged / BotJoined).
     *
     * @return array{
     *     permissions: ?array,   // آرایهٔ خام صلاحیت‌ها (اگر آرایه/رشته بود)
     *     keys: array,           // کلیدهای صلاحیت
     *     has_send_messages: bool|null,  // null = قابل‌تشخیص نبود
     *     is_admin: bool|null,
     *     raw: string            // نمایش امن (trunced)
     * }
     */
    public static function extractPermissions(array $event): array
    {
        $permissions = null;
        $keys = [];

        foreach (self::PERMISSION_KEYS as $key) {
            if (isset($event[$key])) {
                $permissions = $event[$key];
                break;
            }
        }

        // دفاع: هر کلیدی که 'permission' درش باشد
        if ($permissions === null) {
            foreach ($event as $key => $value) {
                if (is_string($key) && str_contains(strtolower($key), 'permission')) {
                    $permissions = $value;
                    break;
                }
            }
        }

        $hasSendMessages = null;
        $isAdmin = null;

        $inspect = function (array $permKeys) use (&$hasSendMessages, &$isAdmin, &$keys): void {
            $keys = array_map('strtolower', array_map('strval', $permKeys));
            $joined = ' ' . implode(' ', array_map('strtolower', array_map('strval', $permKeys))) . ' ';

            // مطابقت فقط با مرزِ واژه (فاصلهٔ قبل) — تا مثلاً «nosendmessage»
            // روی needle مثبتِ «sendmessage» مثبتِ کاذب ندهد.
            $boundaryMatch = static function (string $joined, array $needles): bool {
                foreach ($needles as $needle) {
                    if (preg_match('/(^|[\s=])' . preg_quote($needle, '/') . '/', $joined)) {
                        return true;
                    }
                }

                return false;
            };

            if ($hasSendMessages === null) {
                if ($boundaryMatch($joined, ['nosendmessage', 'no_send_message', 'cansend=false', 'can_send=false', 'canpost=false', 'can_post=false'])) {
                    $hasSendMessages = false;
                } elseif ($boundaryMatch($joined, ['send_message', 'send_messages', 'sendmessage', 'sendpost', 'post_message'])) {
                    $hasSendMessages = true;
                }
            }
            if ($isAdmin === null) {
                $isAdmin = $boundaryMatch($joined, ['isadmin', 'admin', 'is_admin', 'administrator']);
            }
        };

        if (is_array($permissions)) {
            $flat = [];
            array_walk_recursive($permissions, function ($value, $key) use (&$flat): void {
                $flat[] = is_int($key) ? (string) $value : $key . '=' . (is_scalar($value) ? (string) $value : gettype($value));
            });
            $inspect(array_keys($permissions) + $flat);
        } elseif (is_string($permissions)) {
            $inspect(explode(',', $permissions));
        }

        $raw = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        if ($raw === false || $raw === 'null') {
            $raw = '';
        }

        return [
            'permissions' => $permissions,
            'keys' => $keys,
            'has_send_messages' => $hasSendMessages,
            'is_admin' => $isAdmin,
            'raw' => mb_substr($raw, 0, 500),
        ];
    }

    // ------------------------------------------------------------------

    /**
     * آرایهٔ update (سطح update یا بدنه).
     */
    private function update(): array
    {
        $u = $this->payload['update'] ?? $this->payload;

        return is_array($u) ? $u : [];
    }

    /**
     * آرایهٔ new_message (یا message به‌عنوان فیلبک دفاعی).
     */
    private function message(): array
    {
        $u = $this->update();
        $m = $u['new_message'] ?? ($u['message'] ?? []);

        return is_array($m) ? $m : [];
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
