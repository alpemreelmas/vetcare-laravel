<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MedicalRecord>
 */
class MedicalRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $complaints = [
            'Annual wellness exam',
            'Limping on left front leg',
            'Not eating for 2 days',
            'Vomiting and diarrhea',
            'Skin irritation and scratching',
            'Coughing and difficulty breathing',
            'Vaccination update',
            'Dental cleaning',
            'Weight loss',
            'Behavioral changes'
        ];

        $examinations = [
            'Normal physical examination. Alert and responsive.',
            'Mild dehydration noted. Heart rate elevated.',
            'Skin lesions present on abdomen. Otherwise normal.',
            'Dental tartar buildup. Gums slightly inflamed.',
            'Lameness grade 2/5 on left forelimb. No swelling.',
            'Respiratory rate elevated. Clear lung sounds.',
            'Body condition score 6/9. Slightly overweight.',
            'Normal examination findings for age.'
        ];

        $assessments = [
            'Healthy animal. Continue current care.',
            'Mild gastroenteritis. Supportive care recommended.',
            'Allergic dermatitis. Antihistamine therapy.',
            'Dental disease grade 2. Cleaning recommended.',
            'Soft tissue injury. Rest and anti-inflammatory.',
            'Upper respiratory infection. Antibiotic therapy.',
            'Obesity. Diet modification needed.',
            'Age-related changes. Monitor closely.'
        ];

        $plans = [
            'Continue current diet and exercise. Return in 1 year.',
            'Bland diet for 3 days. Recheck if not improving.',
            'Antihistamine daily. Avoid known allergens.',
            'Schedule dental cleaning. Start dental chews.',
            'Restrict activity for 2 weeks. Pain medication.',
            'Antibiotic course for 10 days. Recheck in 1 week.',
            'Prescription diet. Weight recheck in 6 weeks.',
            'Monitor appetite and activity. Return if concerns.'
        ];

        return [
            'appointment_id' => null, // Will be set by relationships
            'pet_id' => Pet::factory(),
            'doctor_id' => Doctor::factory(),
            'visit_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'chief_complaint' => $this->faker->randomElement($complaints),
            'history_of_present_illness' => $this->faker->optional(0.8)->paragraph(),
            'physical_examination' => $this->faker->randomElement($examinations),
            'weight' => $this->faker->optional(0.9)->randomFloat(2, 2.0, 50.0),
            'temperature' => $this->faker->optional(0.8)->randomFloat(1, 37.5, 39.5),
            'heart_rate' => $this->faker->optional(0.8)->numberBetween(60, 180),
            'respiratory_rate' => $this->faker->optional(0.8)->numberBetween(10, 40),
            'assessment' => $this->faker->randomElement($assessments),
            'plan' => $this->faker->randomElement($plans),
            'notes' => $this->faker->optional(0.7)->sentence(),
            'follow_up_instructions' => $this->faker->optional(0.6)->sentence(),
            'next_visit_date' => $this->faker->optional(0.3)->dateTimeBetween('+1 week', '+3 months')?->format('Y-m-d'),
            'status' => $this->faker->randomElement(['draft', 'completed', 'reviewed']),
        ];
    }

    /**
     * Create a medical record for a specific appointment.
     */
    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_id' => $appointment->id,
            'pet_id' => $appointment->pet_id,
            'doctor_id' => $appointment->doctor_id,
            'visit_date' => $appointment->start_datetime->format('Y-m-d'),
        ]);
    }

    /**
     * Create a completed medical record.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Create an emergency visit record.
     */
    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'chief_complaint' => $this->faker->randomElement([
                'Hit by car - trauma',
                'Severe vomiting and collapse',
                'Difficulty breathing',
                'Seizure activity',
                'Bloated abdomen',
                'Severe laceration',
            ]),
            'history_of_present_illness' => 'Emergency presentation with acute symptoms requiring immediate attention.',
        ]);
    }

    /**
     * Create a routine checkup record.
     */
    public function routine(): static
    {
        return $this->state(fn (array $attributes) => [
            'chief_complaint' => 'Annual wellness examination',
            'assessment' => 'Healthy animal. No concerns noted.',
            'plan' => 'Continue current care. Return in 1 year for wellness exam.',
        ]);
    }

    /**
     * Create a record with complete vital signs.
     */
    public function withVitalSigns(): static
    {
        return $this->state(fn (array $attributes) => [
            'weight' => $this->faker->randomFloat(2, 2.0, 50.0),
            'temperature' => $this->faker->randomFloat(1, 37.5, 39.5),
            'heart_rate' => $this->faker->numberBetween(60, 180),
            'respiratory_rate' => $this->faker->numberBetween(10, 40),
        ]);
    }

    /**
     * Create a record with follow-up scheduled.
     */
    public function withFollowUp(): static
    {
        return $this->state(fn (array $attributes) => [
            'follow_up_instructions' => $this->faker->sentence(),
            'next_visit_date' => $this->faker->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
        ]);
    }
} 