<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appointmentTypes = ['regular', 'emergency', 'surgery', 'vaccination', 'checkup', 'consultation'];
        $statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no-show'];
        $durations = [15, 20, 30, 45, 60, 90]; // in minutes

        $duration = fake()->randomElement($durations);
        $startDateTime = fake()->dateTimeBetween('-1 month', '+2 months');
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$duration} minutes");

        $notes = [
            'Regular checkup appointment',
            'Follow-up visit for previous treatment',
            'Vaccination due',
            'Skin condition examination',
            'Dental cleaning required',
            'Weight management consultation',
            'Behavioral assessment needed',
            'Post-surgery follow-up',
            null // Some appointments may not have notes
        ];

        return [
            'doctor_id' => Doctor::factory(),
            'user_id' => User::factory(),
            'pet_id' => Pet::factory(),
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'appointment_type' => fake()->randomElement($appointmentTypes),
            'duration' => $duration,
            'notes' => fake()->randomElement($notes),
            'status' => fake()->randomElement($statuses),
        ];
    }
}
