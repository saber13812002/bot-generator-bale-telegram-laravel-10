<?php

namespace Tests\Unit;

use App\Services\Rubika\RubikaUpdateNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Test‌های واحد نرمال‌ساز آپدیت روبیکا (BM-RUBIKA-DIAGNOSTIC-BOT).
 *
 * همهٔ سناریوها با بدنه‌های دفاعی (شکل‌های مستند + شکل‌های فرضیِ
 * مستندات رسمی محلی) — بدون وابستگی به HTTP/DB.
 */
class RubikaUpdateNormalizerTest extends TestCase
{
    // ------------------------------------------------------------------
    // isRubikaUpdate
    // ------------------------------------------------------------------

    public function test_recognizes_documented_envelope(): void
    {
        $this->assertTrue(RubikaUpdateNormalizer::isRubikaUpdate([
            'update' => ['type' => 'NewMessage', 'chat_id' => 'b0QFtn0C1I'],
        ]));
    }

    public function test_recognizes_bare_update(): void
    {
        $this->assertTrue(RubikaUpdateNormalizer::isRubikaUpdate([
            'type' => 'BotJoined', 'chat_id' => 'c123',
        ]));
    }

    public function test_rejects_non_update_payload(): void
    {
        $this->assertFalse(RubikaUpdateNormalizer::isRubikaUpdate(['foo' => 'bar']));
        $this->assertFalse(RubikaUpdateNormalizer::isRubikaUpdate(['update' => 'not-array']));
        $this->assertFalse(RubikaUpdateNormalizer::isRubikaUpdate(['update' => ['no_type' => 1]]));
        $this->assertFalse(RubikaUpdateNormalizer::isRubikaUpdate([]));
    }

    // ------------------------------------------------------------------
    // accessors پایه
    // ------------------------------------------------------------------

    public function test_readmes_basic_message_fields(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => 'b0QFtn0C1I',
                'new_message' => [
                    'message_id' => '204215121115944300',
                    'text' => 'سلام',
                    'time' => '1643122902',
                    'sender_type' => 'User',
                    'sender_id' => 'u0QFtn0',
                ],
            ],
        ]);

        $this->assertTrue($n->isMessage());
        $this->assertFalse($n->isChatEvent());
        $this->assertSame('NewMessage', $n->type());
        $this->assertSame('b0QFtn0C1I', $n->chatId());
        $this->assertSame('204215121115944300', $n->messageId());
        $this->assertSame('u0QFtn0', $n->senderId());
        $this->assertSame('User', $n->senderType());
        $this->assertSame('سلام', $n->text());
        $this->assertSame('1643122902', $n->time());
    }

    public function test_falls_back_to_new_message_chat_id(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => [
                'type' => 'NewMessage',
                'new_message' => ['chat_id' => 'nestedChat'],
            ],
        ]);

        $this->assertSame('nestedChat', $n->chatId());
    }

    public function test_updated_message_is_message(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => ['type' => 'UpdatedMessage', 'chat_id' => 'c1'],
        ]);

        $this->assertTrue($n->isMessage());
    }

    // ------------------------------------------------------------------
    // رویدادهای چت
    // ------------------------------------------------------------------

    public function test_unknown_event_with_chat_is_chat_event_not_ignored(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => ['type' => 'BotJoined', 'chat_id' => 'chan001'],
        ]);

        $this->assertFalse($n->isMessage());
        $this->assertTrue($n->isChatEvent());
        $this->assertSame('BotJoined', $n->type());
    }

    public function test_event_without_chat_id_is_not_chat_event(): void
    {
        $n = new RubikaUpdateNormalizer(['update' => ['type' => 'SomeEvent']]);

        $this->assertFalse($n->isChatEvent());
        $this->assertFalse($n->isMessage());
    }

    // ------------------------------------------------------------------
    // فوروارد — مبدأ ≠ چت گیرنده
    // ------------------------------------------------------------------

    public function test_forwarded_from_array_mechanism(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => 'receiverGroup',
                'new_message' => [
                    'message_id' => '2',
                    'text' => 'fwd',
                    'forwarded_from' => [
                        'type_from' => 'Channel',
                        'from_chat_id' => 'originalChannel',
                        'from_sender_id' => 'originalBot',
                        'from_message_id' => '99',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($n->isForwarded());

        $f = $n->forwardedInfo();
        $this->assertSame('Channel', $f['source_type']);
        $this->assertSame('originalChannel', $f['from_chat_id']);
        $this->assertSame('originalBot', $f['from_sender_id']);
        $this->assertSame('99', $f['original_message_id']);
        $this->assertSame('forwarded_from', $f['source']);
        // چت گیرنده با چت مبدأ یکی شمرده نمی‌شود
        $this->assertNotSame($n->chatId(), $f['from_chat_id']);
    }

    public function test_direct_fields_mechanism(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => 'receiverPrivate',
                'new_message' => [
                    'message_id' => '3',
                    'type_from' => 'User',
                    'from_chat_id' => 'userOriginChat',
                    'from_sender_id' => 'userOriginSender',
                    'from_message_id' => '42',
                ],
            ],
        ]);

        $this->assertTrue($n->isForwarded());

        $f = $n->forwardedInfo();
        $this->assertSame('User', $f['source_type']);
        $this->assertSame('userOriginChat', $f['from_chat_id']);
        $this->assertSame('userOriginSender', $f['from_sender_id']);
        $this->assertSame('42', $f['original_message_id']);
        $this->assertSame('direct_fields', $f['source']);
    }

    public function test_non_forwarded_message(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => [
                'type' => 'NewMessage',
                'chat_id' => 'c1',
                'new_message' => ['message_id' => '1', 'text' => 'hi'],
            ],
        ]);

        $this->assertFalse($n->isForwarded());
        $this->assertSame('none', $n->forwardedInfo()['source']);
        $this->assertNull($n->forwardedInfo()['from_chat_id']);
    }

    // ------------------------------------------------------------------
    // getChat — خوانش دفاعی
    // ------------------------------------------------------------------

    public function test_extract_chat_from_direct_response(): void
    {
        $chat = RubikaUpdateNormalizer::extractChatFromGetChatResponse([
            'chat' => ['id' => 'c1', 'type' => 'group', 'title' => 'گروه تست', 'member_count' => 5],
        ]);

        $this->assertNotNull($chat);
        $fields = RubikaUpdateNormalizer::chatFields($chat);
        $this->assertSame('c1', $fields['id']);
        $this->assertSame('group', $fields['type']);
        $this->assertSame('گروه تست', $fields['title']);
        $this->assertSame('5', $fields['member_count']);
    }

    public function test_extract_chat_from_data_envelope(): void
    {
        $chat = RubikaUpdateNormalizer::extractChatFromGetChatResponse([
            'status' => 'ok',
            'data' => ['chat' => ['id' => 'c2', 'type' => 'channel', 'username' => 'mychannel']],
        ]);

        $this->assertNotNull($chat);
        $this->assertSame('c2', $chat['id']);
    }

    public function test_extract_chat_returns_null_on_unknown_shape(): void
    {
        $this->assertNull(RubikaUpdateNormalizer::extractChatFromGetChatResponse(['foo' => 'bar']));
        $this->assertNull(RubikaUpdateNormalizer::extractChatFromGetChatResponse([]));
    }

    public function test_chat_fields_are_dash_safe_for_missing_fields(): void
    {
        $fields = RubikaUpdateNormalizer::chatFields(['id' => 'c9']);

        $this->assertSame('c9', $fields['id']);
        $this->assertNull($fields['type']);
        $this->assertNull($fields['title']);
        $this->assertNull($fields['username']);
    }

    public function test_chat_type_labels(): void
    {
        $this->assertSame('User (خصوصی کاربر)', RubikaUpdateNormalizer::chatTypeLabel('User'));
        $this->assertSame('Bot (خصوصی ربات)', RubikaUpdateNormalizer::chatTypeLabel('bot'));
        $this->assertSame('Group (گروه)', RubikaUpdateNormalizer::chatTypeLabel('GROUP'));
        $this->assertSame('Channel (کانال)', RubikaUpdateNormalizer::chatTypeLabel('channel'));
        $this->assertSame('—', RubikaUpdateNormalizer::chatTypeLabel(null));
        $this->assertSame('mystery', RubikaUpdateNormalizer::chatTypeLabel('mystery'));
    }

    // ------------------------------------------------------------------
    // صلاحیت‌ها — خوانش دفاعی
    // ------------------------------------------------------------------

    public function test_permissions_with_send_message_key(): void
    {
        $perm = RubikaUpdateNormalizer::extractPermissions([
            'chat_id' => 'c1',
            'permissions' => ['send_message' => true, 'delete_message' => true],
        ]);

        $this->assertTrue($perm['has_send_messages']);
        $this->assertContains('send_message', $perm['keys']);
    }

    public function test_permissions_with_negative_needle(): void
    {
        $perm = RubikaUpdateNormalizer::extractPermissions([
            'permissions' => ['nosendmessage' => true],
        ]);

        $this->assertFalse($perm['has_send_messages']);
    }

    public function test_permissions_string_form(): void
    {
        $perm = RubikaUpdateNormalizer::extractPermissions([
            'bot_permissions' => 'send_messages, post_message',
        ]);

        $this->assertTrue($perm['has_send_messages']);
    }

    public function test_permissions_admin_detection(): void
    {
        $perm = RubikaUpdateNormalizer::extractPermissions([
            'permissions' => ['is_admin' => true],
        ]);

        $this->assertTrue($perm['is_admin']);
        $this->assertNull($perm['has_send_messages']);
    }

    public function test_permissions_absent_returns_nulls_not_guesses(): void
    {
        $perm = RubikaUpdateNormalizer::extractPermissions(['chat_id' => 'c1']);

        $this->assertNull($perm['permissions']);
        $this->assertNull($perm['has_send_messages']);
        $this->assertNull($perm['is_admin']);
        $this->assertSame([], $perm['keys']);
        $this->assertSame('', $perm['raw']);
    }

    public function test_defensive_permission_key_containing_permission(): void
    {
        $perm = RubikaUpdateNormalizer::extractPermissions([
            'new_permissions' => 'send_messages',
        ]);

        $this->assertTrue($perm['has_send_messages']);
    }

    // ------------------------------------------------------------------
    // rawUpdate
    // ------------------------------------------------------------------

    public function test_raw_update_returns_unwrapped_update(): void
    {
        $n = new RubikaUpdateNormalizer([
            'update' => ['type' => 'NewMessage', 'chat_id' => 'c1'],
        ]);

        $this->assertSame('c1', $n->rawUpdate()['chat_id']);
    }
}
