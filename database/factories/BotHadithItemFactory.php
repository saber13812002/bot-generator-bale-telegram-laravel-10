<?php

namespace Database\Factories;

use App\Models\BotHadithItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BotHadithItemFactory extends Factory
{
    protected $model = BotHadithItem::class;

    public function definition(): array
    {
        return [
            'id2' => $this->faker->word(),
            'arabic' => $this->faker->word(),
            'persian' => $this->faker->word(),
            'english' => $this->faker->word(),
            'book' => $this->faker->word(),
            'number' => $this->faker->randomNumber(),
            'part' => $this->faker->word(),
            'chapter' => $this->faker->word(),
            'section' => $this->faker->word(),
            'volume' => $this->faker->word(),
//            'tags' => $this->faker->words(3),
//            'related' => $this->faker->words(3),
//            'history' => $this->faker->words(3),
//            'gradings' => $this->faker->words(3),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
