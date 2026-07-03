<?php

namespace App\Modules\BotCreation\Services;

use App\Helpers\BotHelper;
use App\Modules\BotCreation\Models\BotCreationSession;
use App\Modules\BotCreation\Models\FieldDefinition;
use Telegram;

/**
 * Renders bot creation wizard steps as chat messages for Telegram/Bale.
 * Used by BotMotherController.
 */
class ChatRenderer
{
    public function __construct(
        private readonly FieldRegistry $fieldRegistry,
    ) {}

    /**
     * Render the intro / endpoint selection message.
     */
    public function renderIntro(Telegram $bot, string $chatId, array $endpoints): void
    {
        $message = "🤖 ربات ساز\n\n";
        $message .= "با این ربات می‌توانید ربات‌های جدید بسازید.\n\n";
        $message .= "📋 لیست ربات‌های قابل ساخت:\n\n";

        foreach ($endpoints as $index => $endpoint) {
            $number = $index + 1;
            $message .= "{$number}. {$endpoint['name']}\n";
            $message .= "   📝 {$endpoint['description']}\n\n";
        }

        $message .= "برای انتخاب، شماره مورد نظر را ارسال کنید.";

        BotHelper::sendMessage($bot, $message);
    }

    /**
     * Render a wizard step as a chat message.
     */
    public function renderStep(Telegram $bot, string $chatId, FieldDefinition $field, array $context): void
    {
        $handler = $this->fieldRegistry->get($field->type);
        $prompt = $handler->renderChatPrompt($field, $context);
        $keyboard = $handler->renderChatKeyboard($field, $context);

        if ($keyboard !== null) {
            $inlineKeyboard = $bot->buildInlineKeyBoard($keyboard);
            BotHelper::sendKeyboardMessageToChatId($bot, $prompt, $inlineKeyboard, $chatId);
        } else {
            BotHelper::sendMessageByChatId($bot, $chatId, $prompt);
        }
    }

    /**
     * Render the completion message.
     */
    public function renderCompletion(Telegram $bot, string $chatId, array $result): void
    {
        if ($result['success']) {
            $botItem = $result['bot'] ?? null;
            $successMessage = "✅ ربات با موفقیت ساخته شد!\n\n";

            if ($botItem) {
                $username = $botItem->telegram_bot_name ?? $botItem->bale_bot_name ?? 'N/A';
                $type = $botItem->type ?? 'telegram';
                $successMessage .= "📝 اطلاعات ربات:\n";
                $successMessage .= "• نام: @{$username}\n";
                $successMessage .= "• نوع: " . ($type === 'telegram' ? 'تلگرام' : 'بله') . "\n";
                $successMessage .= "• Bot ID: {$botItem->id}\n\n";
            }

            $successMessage .= "💡 برای مشاهده لیست دستورات: /help";
            BotHelper::sendMessage($bot, $successMessage);
        } else {
            $errorMessage = "❌ خطا در ساخت ربات:\n\n";
            $errorMessage .= ($result['error'] ?? 'خطای ناشناخته') . "\n\n";
            $errorMessage .= "لطفاً دوباره تلاش کنید یا با ادمین تماس بگیرید.";
            BotHelper::sendMessage($bot, $errorMessage);
        }
    }

    /**
     * Render an error message for a step.
     */
    public function renderStepError(Telegram $bot, string $chatId, string $error, FieldDefinition $field): void
    {
        $message = "❌ {$error}\n\n";
        $message .= "لطفاً دوباره تلاش کنید.";

        // Re-render the prompt
        $handler = $this->fieldRegistry->get($field->type);
        $prompt = $handler->renderChatPrompt($field, []);
        $message .= "\n\n{$prompt}";

        BotHelper::sendMessageByChatId($bot, $chatId, $message);
    }
}
