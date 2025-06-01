<?php

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Diagnosis>
 */
class DiagnosisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $diagnoses = [
            ['code' => 'K59.0', 'name' => 'Gastroenteritis', 'description' => 'Inflammation of stomach and intestines'],
            ['code' => 'L20.9', 'name' => 'Allergic Dermatitis', 'description' => 'Skin inflammation due to allergic reaction'],
            ['code' => 'M25.5', 'name' => 'Joint Pain', 'description' => 'Pain in joint areas'],
            ['code' => 'J06.9', 'name' => 'Upper Respiratory Infection', 'description' => 'Infection of upper respiratory tract'],
            ['code' => 'K59.1', 'name' => 'Dental Disease', 'description' => 'Periodontal disease and tooth decay'],
            ['code' => 'E66.9', 'name' => 'Obesity', 'description' => 'Excessive body weight'],
            ['code' => 'S06.9', 'name' => 'Soft Tissue Injury', 'description' => 'Injury to muscles, tendons, or ligaments'],
            ['code' => 'H66.9', 'name' => 'Ear Infection', 'description' => 'Infection of the ear canal'],
            ['code' => 'N39.0', 'name' => 'Urinary Tract Infection', 'description' => 'Bacterial infection of urinary system'],
            ['code' => 'K92.2', 'name' => 'Intestinal Parasites', 'description' => 'Parasitic worms in digestive system'],
        ];

        $selectedDiagnosis = $this->faker->randomElement($diagnoses);

        return [
            'medical_record_id' => MedicalRecord::factory(),
            'pet_id' => Pet::factory(),
            'diagnosis_code' => $selectedDiagnosis['code'],
            'diagnosis_name' => $selectedDiagnosis['name'],
            'description' => $selectedDiagnosis['description'],
            'severity' => $this->faker->randomElement(['mild', 'moderate', 'severe', 'critical']),
            'status' => $this->faker->randomElement(['active', 'resolved', 'chronic', 'monitoring']),
            'diagnosed_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'resolved_date' => $this->faker->optional(0.3)->dateTimeBetween('now', '+6 months')?->format('Y-m-d'),
            'notes' => $this->faker->optional(0.6)->sentence(),
        ];
    }

    /**
     * Create a diagnosis for a specific medical record.
     */
    public function forMedicalRecord(MedicalRecord $medicalRecord): static
    {
        return $this->state(fn (array $attributes) => [
            'medical_record_id' => $medicalRecord->id,
            'pet_id' => $medicalRecord->pet_id,
            'diagnosed_date' => $medicalRecord->visit_date,
        ]);
    }

    /**
     * Create an active diagnosis.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'resolved_date' => null,
        ]);
    }

    /**
     * Create a resolved diagnosis.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'resolved_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Create a chronic diagnosis.
     */
    public function chronic(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'chronic',
            'severity' => $this->faker->randomElement(['moderate', 'severe']),
            'resolved_date' => null,
        ]);
    }

    /**
     * Create a severe diagnosis.
     */
    public function severe(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'severe',
            'status' => 'active',
        ]);
    }

    /**
     * Create a mild diagnosis.
     */
    public function mild(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => 'mild',
        ]);
    }
} 