<?php

namespace Database\Factories;

use App\Models\RssItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RssItem>
 */
class RssItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->name(),
            'description' => fake()->name(),
            'url' => fake()->url(),
            'is_active' => 0,
        ];
    }
}
