<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class TextField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'text';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        if ($definition->required && (empty($value) || !is_string($value))) {
            return ['valid' => false, 'message' => 'این فیلد الزامی است.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $message = "📝 {$field->label}";
        if ($field->help) {
            $message .= "\n\n💡 {$field->help}";
        }
        if ($field->placeholder) {
            $message .= "\n\nمثال: {$field->placeholder}";
        }

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        return null; // No keyboard for text input
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        $required = $field->required ? 'required' : '';
        $placeholder = $field->placeholder ? "placeholder=\"{$field->placeholder}\"" : '';

        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            <input type="text" name="{$field->id}" {$required} {$placeholder}
                   class="w-full border rounded px-3 py-2 font-mono text-sm">
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        return is_string($value) ? trim($value) : $value;
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
