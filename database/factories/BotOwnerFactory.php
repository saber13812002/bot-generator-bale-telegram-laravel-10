<?php

namespace Database\Factories;

use App\Modules\BotOwner\Models\BotOwner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\BotOwner\Models\BotOwner>
 */
class BotOwnerFactory extends Factory
{
    protected $model = BotOwner::class;

    public function definition(): array
    {
        return [
            'phone' => $this->faker->numerify('09##########'),
            'name' => $this->faker->name(),
            'bale_chat_id' => $this->faker->numerify('##########'),
            'is_pro' => false,
            'pro_expires_at' => null,
            'status' => 'active',
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => true,
            'pro_expires_at' => now()->addYear(),
            'pro_confirmed_at' => now(),
        ]);
    }

    public function proUnlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => true,
            'pro_expires_at' => null,
            'pro_confirmed_at' => now(),
        ]);
    }

    public function proExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pro' => false,
            'pro_expires_at' => now()->subDay(),
        ]);
    }
}
