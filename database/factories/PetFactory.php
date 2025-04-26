<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pet>
 */
class PetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'species' => $this->faker->word(),
            'breed' => $this->faker->word(),
            'date_of_birth' => $this->faker->date(),
            'weight' => $this->faker->randomFloat(2, 0, 100),
            'gender' => $this->faker->randomElement(GenderEnum::cases())->value,
        ];
    }
}
