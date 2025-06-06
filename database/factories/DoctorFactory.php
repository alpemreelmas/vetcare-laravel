<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'specialization' => fake()->randomElement(['Cardiologist', 'Dermatologist', 'Endocrinologist', 'Gastroenterologist', 'Hematologist', 'Neurologist', 'Oncologist', 'Pediatrician', 'Psychiatrist', 'Urologist']),
            'license_number' => fake()->unique()->randomNumber(9),
            'phone_number' => fake()->phoneNumber(),
            'biography' => fake()->paragraph(),
            'working_hours' => "9:00-15:00",
            'user_id' => User::factory()->create()->id,
        ];
    }
}
