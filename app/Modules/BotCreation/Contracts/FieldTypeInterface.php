<?php

namespace App\Modules\BotCreation\Contracts;

use App\Modules\BotCreation\Models\FieldDefinition;

interface FieldTypeInterface
{
    /**
     * Get the unique type identifier for this field.
     */
    public function getType(): string;

    /**
     * Validate the value for this field type.
     *
     * @return array{valid: bool, message: ?string}
     */
    public function validate(mixed $value, FieldDefinition $definition): array;

    /**
     * Render a chat message / prompt for this field (Bot Mother).
     */
    public function renderChatPrompt(FieldDefinition $field, array $context): string;

    /**
     * Render chat keyboard buttons, if applicable.
     */
    public function renderChatKeyboard(FieldDefinition $field, array $context): ?array;

    /**
     * Render a web input HTML fragment for this field.
     */
    public function renderWebHtml(FieldDefinition $field, array $context): string;

    /**
     * Process and normalize the value after collection.
     */
    public function processValue(mixed $value, FieldDefinition $definition): mixed;

    /**
     * Determine if this field type supports async (AJAX) validation.
     */
    public function supportsAsyncValidation(): bool;

    /**
     * Async validation logic, e.g., token check via API.
     *
     * @return array{valid: bool, message: ?string, data?: array}
     */
    public function asyncValidate(mixed $value, FieldDefinition $definition): array;
}
