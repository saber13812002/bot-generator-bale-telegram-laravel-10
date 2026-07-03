<?php

namespace App\Modules\BotCreation\Services;

use App\Modules\BotCreation\Contracts\FieldTypeInterface;

/**
 * Registry of all available field types for the bot creation workflow.
 * New field types can be registered here.
 */
class FieldRegistry
{
    /** @var array<string, FieldTypeInterface> */
    private array $fields = [];

    /**
     * Register a field type handler.
     */
    public function register(FieldTypeInterface $handler): void
    {
        $this->fields[$handler->getType()] = $handler;
    }

    /**
     * Get a field type handler by type string.
     */
    public function get(string $type): FieldTypeInterface
    {
        if (!isset($this->fields[$type])) {
            throw new \InvalidArgumentException("Unknown field type: {$type}");
        }

        return $this->fields[$type];
    }

    /**
     * Check if a field type is registered.
     */
    public function has(string $type): bool
    {
        return isset($this->fields[$type]);
    }

    /**
     * Get all registered field types.
     *
     * @return array<string, FieldTypeInterface>
     */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * Register all default/built-in field types.
     */
    public function registerDefaults(): void
    {
        $this->register(new \App\Modules\BotCreation\Fields\TextField());
        $this->register(new \App\Modules\BotCreation\Fields\SelectField());
        $this->register(new \App\Modules\BotCreation\Fields\MultiLineTextField());
        $this->register(new \App\Modules\BotCreation\Fields\CollectionField());
        $this->register(new \App\Modules\BotCreation\Fields\ConfirmField());
        $this->register(new \App\Modules\BotCreation\Fields\ForwardField());
        $this->register(new \App\Modules\BotCreation\Fields\YesNoField());
        $this->register(new \App\Modules\BotCreation\Fields\NumberField());
        $this->register(new \App\Modules\BotCreation\Fields\BulkTextField());
        $this->register(new \App\Modules\BotCreation\Fields\ComplexWizardField());
    }
}
