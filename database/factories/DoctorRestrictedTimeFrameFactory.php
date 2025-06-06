<?php

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DoctorRestrictedTimeFrame>
 */
class DoctorRestrictedTimeFrameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reasons = [
            'Vacation',
            'Medical Conference',
            'Personal Leave',
            'Training Session',
            'Emergency Surgery',
            'Sick Leave',
            'Family Emergency',
            'Continuing Education',
            'Equipment Maintenance',
            'Administrative Meeting'
        ];

        $startDateTime = fake()->dateTimeBetween('now', '+6 months');
        $endDateTime = clone $startDateTime;
        
        // Random duration between 1 hour and 7 days
        $durationHours = fake()->numberBetween(1, 168);
        $endDateTime->modify("+{$durationHours} hours");

        return [
            'doctor_id' => Doctor::factory(),
            'start_datetime' => $startDateTime,
            'end_datetime' => $endDateTime,
            'reason' => fake()->randomElement($reasons),
        ];
    }
} 