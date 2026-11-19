<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class ConfirmField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'confirm';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        $normalized = mb_strtolower(trim((string) $value));
        $validValues = ['بله', 'yes', 'y', '1', 'true'];

        if ($definition->required && !in_array($normalized, $validValues, true)) {
            return ['valid' => false, 'message' => 'لطفاً تأیید کنید.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $message = "📝 {$field->label}\n\n";
        if ($field->help) {
            $message .= "💡 {$field->help}\n\n";
        }
        $message .= "لطفاً 'بله' را ارسال کنید تا ادامه دهیم.";

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        return [
            [
                ['text' => '✅ بله', 'callback_data' => "wf:{$field->id}:yes"],
            ],
        ];
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            <p class="text-sm text-gray-600 mb-2">{$field->help}</p>
            <div class="flex gap-4">
                <label class="inline-flex items-center">
                    <input type="radio" name="{$field->id}" value="yes" class="form-radio">
                    <span class="mr-2">بله</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="{$field->id}" value="no" class="form-radio">
                    <span class="mr-2">خیر</span>
                </label>
            </div>
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['بله', 'yes', 'y', '1', 'true'], true);
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
