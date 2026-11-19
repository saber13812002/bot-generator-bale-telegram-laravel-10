<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class BulkTextField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'bulk_text';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        if ($definition->required && (empty($value) || !is_array($value))) {
            return ['valid' => false, 'message' => 'حداقل یک آیتم الزامی است.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $message = "📝 {$field->label}\n\n";
        if ($field->formatHint) {
            $message .= "📋 فرمت: {$field->formatHint}\n\n";
        }
        if ($field->help) {
            $message .= "💡 {$field->help}\n\n";
        }
        $message .= "می‌توانید چندین آیتم را در یک یا چند پیام ارسال کنید.\n";
        $message .= "وقتی تمام شد، کلمه 'پایان' را ارسال کنید.";

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        return null;
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        $formatHint = $field->formatHint ? "<p class=\"text-xs text-gray-500 mb-2\">{$field->formatHint}</p>" : '';

        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            {$formatHint}
            <textarea name="{$field->id}" rows="6"
                      class="w-full border rounded px-3 py-2 text-sm font-mono"></textarea>
            <p class="text-xs text-gray-400 mt-1">هر خط یک آیتم جداگانه محسوب می‌شود</p>
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode("\n", $value))));
        }

        return is_array($value) ? array_values(array_filter($value)) : [];
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
