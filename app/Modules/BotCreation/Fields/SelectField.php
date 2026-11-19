<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class SelectField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'select';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        if ($definition->required && empty($value)) {
            return ['valid' => false, 'message' => 'این فیلد الزامی است.'];
        }

        $validValues = array_column($definition->options ?? [], 'value');
        if (!empty($validValues) && !in_array($value, $validValues, true)) {
            return ['valid' => false, 'message' => 'مقدار انتخاب شده نامعتبر است.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $message = "📝 {$field->label}\n\n";
        if ($field->help) {
            $message .= "💡 {$field->help}\n\n";
        }

        $options = $field->options ?? [];
        foreach ($options as $i => $option) {
            $num = $i + 1;
            $label = $option['label_fa'] ?? $option['label_en'] ?? $option['label'] ?? $option['value'];
            $message .= "{$num}. {$label}\n";
        }

        $message .= "\nشماره مورد نظر را ارسال کنید:";

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        $options = $field->options ?? [];
        if (empty($options)) {
            return null;
        }

        $buttons = [];
        $row = [];
        foreach ($options as $i => $option) {
            $label = $option['label_fa'] ?? $option['label_en'] ?? $option['label'] ?? $option['value'];
            $value = $option['value'];
            $row[] = [
                'text' => $label,
                'callback_data' => "wf:{$field->id}:{$value}",
            ];
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row;
        }

        return $buttons;
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        $options = $field->options ?? [];
        $optionsHtml = '';
        foreach ($options as $option) {
            $label = $option['label_fa'] ?? $option['label_en'] ?? $option['label'] ?? $option['value'];
            $value = $option['value'];
            $optionsHtml .= "<option value=\"{$value}\">{$label}</option>\n";
        }

        $required = $field->required ? 'required' : '';

        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            <select name="{$field->id}" {$required} class="w-full border rounded px-3 py-2">
                {$optionsHtml}
            </select>
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        // Map numeric selection to actual value
        if (is_numeric($value)) {
            $options = $definition->options ?? [];
            $idx = (int) $value - 1;
            if (isset($options[$idx])) {
                return $options[$idx]['value'];
            }
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
