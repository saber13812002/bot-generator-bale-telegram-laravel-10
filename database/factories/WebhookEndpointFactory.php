<?php

namespace Database\Factories;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'endpoint_id' => $this->faker->unique()->regexify('[a-z-]{10,20}'),
            'name' => $this->faker->words(3, true),
            'route' => 'webhook-' . $this->faker->regexify('[a-z-]{5,15}'),
            'description' => $this->faker->sentence(),
            'requires_bot_mother_id' => true,
            'requires_token' => true,
            'requires_language' => false,
            'supports_multiple_languages' => false,
            'is_active' => true,
            'wizard_steps' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withLanguage(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_language' => true,
            'supports_multiple_languages' => true,
        ]);
    }
}
