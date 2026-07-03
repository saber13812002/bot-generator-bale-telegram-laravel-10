<?php

namespace App\Modules\BotCreation\Fields;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;
use App\Modules\BotCreation\Models\FieldDefinition;

/**
 * Handles complex multi-step sub-wizards (e.g., psychology test questions + category descriptions).
 * The sub-wizard steps are defined in the field's 'wizard' config.
 */
class ComplexWizardField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'complex_wizard';
    }

    public function validate(mixed $value, FieldDefinition $definition): array
    {
        return ['valid' => true, 'message' => null];
    }

    public function renderChatPrompt(FieldDefinition $field, array $context): string
    {
        $subSteps = $field->getSubWizardSteps();
        $subStepIndex = $context['sub_step_index'] ?? 0;
        $subStep = $subSteps[$subStepIndex] ?? null;

        if (!$subStep) {
            return "📝 {$field->label}";
        }

        return match ($subStep['type'] ?? 'text') {
            'bulk_text' => $this->renderBulkTextSubStep($field, $subStep, $context),
            'repeated_text' => $this->renderRepeatedTextSubStep($field, $subStep, $context),
            'confirmation' => $this->renderConfirmationSubStep($field, $subStep, $context),
            default => "📝 {$field->label}: " . ($subStep['label_fa'] ?? $subStep['label'] ?? ''),
        };
    }

    private function renderBulkTextSubStep(FieldDefinition $field, array $subStep, array $context): string
    {
        $message = "📝 " . ($subStep['label_fa'] ?? $subStep['label'] ?? $field->label) . "\n\n";
        $formatHint = $subStep['format_hint_fa'] ?? $subStep['format_hint'] ?? $field->formatHint;
        if ($formatHint) {
            $message .= "📋 {$formatHint}\n\n";
        }
        $message .= "می‌توانید در یک یا چند پیام ارسال کنید.\n";
        $message .= "وقتی تمام شد، 'پایان' را ارسال کنید.";

        return $message;
    }

    private function renderRepeatedTextSubStep(FieldDefinition $field, array $subStep, array $context): string
    {
        $current = $context['repeated_index'] ?? 0;
        $total = $context['repeated_total'] ?? 0;
        $item = $context['repeated_item'] ?? '';

        $message = "📝 " . ($subStep['label_fa'] ?? $subStep['label'] ?? $field->label) . "\n\n";
        if ($total > 0) {
            $message .= "مرحله " . ($current + 1) . " از {$total}\n\n";
        }
        if ($item) {
            $message .= "برای '{$item}':\n";
        }
        $message .= "لطفاً متن مورد نظر را ارسال کنید:";

        return $message;
    }

    private function renderConfirmationSubStep(FieldDefinition $field, array $subStep, array $context): string
    {
        return "📝 " . ($subStep['label_fa'] ?? $subStep['label'] ?? 'پایان و ثبت') . "\n\nهمه موارد ثبت شد. در حال نهای‌سازی...";
    }

    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array
    {
        return null;
    }

    public function renderWebHtml(FieldDefinition $field, array $context): string
    {
        return <<<HTML
        <div class="mb-4 p-4 bg-gray-50 rounded">
            <label class="block text-sm font-medium mb-2">{$field->label}</label>
            <p class="text-sm text-gray-600">این بخش نیازمند ویزارد چندمرحله‌ای است.</p>
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
