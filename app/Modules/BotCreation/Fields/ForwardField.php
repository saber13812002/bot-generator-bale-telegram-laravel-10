<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class ForwardField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'forward';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        // Forward is always optional in terms of text validation;
        // the actual validation happens server-side when parsing the update
        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $message = "📝 {$field->label}\n\n";
        if ($field->help) {
            $message .= "💡 {$field->help}\n\n";
        }

        $skipText = $field->optional ? "\n\n(می‌توانید «رد کن» بفرستید تا این مرحله رد شود)" : '';

        $message .= "یک پیام از مکان مورد نظر فوروارد کنید.{$skipText}";

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        if ($field->optional) {
            return [
                [
                    ['text' => '⏭ رد کن', 'callback_data' => "wf:{$field->id}:skip"],
                ],
            ];
        }

        return null;
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            <p class="text-sm text-gray-600 mb-2">{$field->help}</p>
            <input type="text" name="{$field->id}"
                   class="w-full border rounded px-3 py-2 text-sm"
                   placeholder="شناسه چت یا آیدی کانال/گروه را وارد کنید">
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        return $value;
    }

    public function supportsAsyncValidation(): bool
    {
        return false;
    }

    public function asyncValidate(mixed $value, FieldDefinition $definition): array
    {
        return ['valid' => true, 'message' => null];
    }
}
