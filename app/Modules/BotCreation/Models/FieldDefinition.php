<?php

namespace App\Modules\BotCreation\Models;

use Illuminate\Support\Collection;

/**
 * Value object representing a single field/step definition in the bot creation wizard.
 */
class FieldDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $required = true,
        public readonly ?int $order = null,
        public readonly ?array $options = null,
        public readonly ?string $placeholder = null,
        public readonly ?string $help = null,
        public readonly ?string $validation = null,
        public readonly ?array $condition = null,
        public readonly ?string $optionsProvider = null,
        public readonly ?array $wizard = null,
        public readonly ?string $wizardType = null,
        public readonly ?string $formatHint = null,
        public readonly ?bool $asyncValidate = null,
        public readonly ?string $onValidate = null,
        public readonly ?array $accepts = null,
        public readonly ?bool $optional = null,
        public readonly ?array $meta = null,
    ) {}

    /**
     * Create from a raw array (as stored in wizard_steps JSON).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? throw new \InvalidArgumentException('Field id is required'),
            type: $data['type'] ?? 'text',
            label: $data['label'] ?? $data['label_fa'] ?? $data['id'],
            required: $data['required'] ?? true,
            order: $data['order'] ?? null,
            options: $data['options'] ?? null,
            placeholder: $data['placeholder'] ?? $data['placeholder_fa'] ?? null,
            help: $data['help'] ?? $data['help_fa'] ?? null,
            validation: $data['validation'] ?? null,
            condition: $data['condition'] ?? null,
            optionsProvider: $data['options_provider'] ?? null,
            wizard: $data['wizard'] ?? null,
            wizardType: $data['wizard_type'] ?? null,
            formatHint: $data['format_hint'] ?? $data['format_hint_fa'] ?? null,
            asyncValidate: $data['async_validate'] ?? null,
            onValidate: $data['on_validate'] ?? null,
            accepts: $data['accepts'] ?? null,
            optional: $data['optional'] ?? null,
            meta: $data['meta'] ?? null,
        );
    }

    /**
     * Create a collection from an array of definitions.
     *
     * @param array $steps
     * @return Collection<int, self>
     */
    public static function collectionFromArray(array $steps): Collection
    {
        $definitions = collect($steps)->map(fn (array $data) => self::fromArray($data));

        return $definitions->sortBy(fn (self $f) => $f->order ?? 999);
    }

    /**
     * Check if this field should be shown given the current context.
     */
    public function shouldShow(array $contextData): bool
    {
        if ($this->condition === null) {
            return true;
        }

        $field = $this->condition['field'] ?? null;
        $operator = $this->condition['operator'] ?? '=';
        $value = $this->condition['value'] ?? null;
        $actual = $contextData[$field] ?? null;

        return match ($operator) {
            '=' => $actual === $value,
            '!=' => $actual !== $value,
            'in' => is_array($value) && in_array($actual, $value, true),
            'not_in' => is_array($value) && !in_array($actual, $value, true),
            default => true,
        };
    }

    /**
     * Check if this field has a multi-step sub-wizard.
     */
    public function hasSubWizard(): bool
    {
        return $this->wizard !== null && ($this->wizardType === 'multi_step');
    }

    /**
     * Get the sub-wizard steps.
     */
    public function getSubWizardSteps(): array
    {
        return $this->wizard['steps'] ?? [];
    }

    /**
     * Convert back to array for serialization.
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'required' => $this->required,
            'order' => $this->order,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'validation' => $this->validation,
            'condition' => $this->condition,
            'options_provider' => $this->optionsProvider,
            'wizard' => $this->wizard,
            'wizard_type' => $this->wizardType,
            'format_hint' => $this->formatHint,
            'async_validate' => $this->asyncValidate,
            'on_validate' => $this->onValidate,
            'accepts' => $this->accepts,
            'optional' => $this->optional,
            'meta' => $this->meta,
        ], fn ($v) => $v !== null);
    }
}
