<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

class CollectionField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'collection';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        if ($definition->required && empty($value)) {
            return ['valid' => false, 'message' => 'حداقل یک آیتم الزامی است.'];
        }

        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $currentCount = $context['collected_count'] ?? 0;
        $message = "📝 {$field->label}\n\n";

        if ($field->help) {
            $message .= "💡 {$field->help}\n\n";
        }

        $accepts = $field->accepts ?? ['text'];
        $message .= "پشتیبانی: " . implode(', ', array_map(fn ($a) => match($a) {
            'text' => 'متن',
            'photo' => 'عکس',
            'video' => 'ویدیو',
            'voice' => 'وییس',
            'audio' => 'صوت (MP3)',
            'document' => 'فایل',
            default => $a,
        }, $accepts)) . "\n\n";

        if ($currentCount > 0) {
            $message .= "✅ {$currentCount} آیتم ثبت شد.\n\n";
        }

        $message .= "آیتم بعدی را ارسال کنید.\n";
        $message .= "وقتی تمام شد، 'پایان' را ارسال کنید.";

        return $message;
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        return null;
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        $accepts = implode(',', $field->accepts ?? ['text']);

        return <<<HTML
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">{$field->label}</label>
            <div id="{$field->id}-container" class="space-y-2">
                <div class="flex gap-2">
                    <input type="text" id="{$field->id}-input"
                           class="flex-1 border rounded px-3 py-2 text-sm"
                           placeholder="متن آیتم را وارد کنید">
                    <button type="button" onclick="addCollectionItem('{$field->id}')"
                            class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm">
                        +
                    </button>
                </div>
                <ul id="{$field->id}-list" class="list-disc list-inside text-sm"></ul>
            </div>
            <input type="hidden" name="{$field->id}" id="{$field->id}-hidden">
            <script>
            function addCollectionItem(fieldId) {
                const input = document.getElementById(fieldId + '-input');
                const list = document.getElementById(fieldId + '-list');
                const hidden = document.getElementById(fieldId + '-hidden');
                if (!input.value.trim()) return;
                const li = document.createElement('li');
                li.textContent = input.value;
                li.className = 'text-gray-700';
                list.appendChild(li);
                const items = JSON.parse(hidden.value || '[]');
                items.push(input.value);
                hidden.value = JSON.stringify(items);
                input.value = '';
            }
            </script>
        </div>
        HTML;
    }

    public function processValue(mixed $value, FieldDefinition $definition): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return array_values(array_filter(array_map('trim', explode("\n", $value))));
        }

        return is_array($value) ? $value : [];
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
