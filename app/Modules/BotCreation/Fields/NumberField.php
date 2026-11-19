<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class NumberField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'number';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        if ($definition->required && $value === null) {
            return ['valid' => false, 'message' => 'این فیلد الزامی است.'];
        }

        if ($value !== null && !is_numeric($value)) {
            return ['valid' => false, 'message' => 'لطفاً یک عدد وارد کنید.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $message = "📝 {$field->label}\n\n";
        if ($field->help) {
            $message .= "💡 {$field->help}\n\n";
        }
        $message .= "لطفاً عدد مورد نظر را ارسال کنید:";

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        return null;
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        $requiredAttr = $field->required ? 'required' : '';
        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            <input type="number" name="{$field->id}" {$requiredAttr}
                   class="w-full border rounded px-3 py-2">
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

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
