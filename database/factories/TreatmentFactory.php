<?php

namespace Database\Factories;

use App\Models\Diagnosis;
use App\Models\MedicalRecord;
use App\Models\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Treatment>
 */
class TreatmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $treatmentTypes = [
            'medication', 'procedure', 'surgery', 'therapy', 
            'vaccination', 'diagnostic_test', 'other'
        ];

        $medications = [
            ['name' => 'Amoxicillin', 'dosage' => '250mg', 'frequency' => 'Twice daily', 'route' => 'Oral'],
            ['name' => 'Prednisone', 'dosage' => '5mg', 'frequency' => 'Once daily', 'route' => 'Oral'],
            ['name' => 'Metacam', 'dosage' => '1.5mg', 'frequency' => 'Once daily', 'route' => 'Oral'],
            ['name' => 'Tramadol', 'dosage' => '50mg', 'frequency' => 'Every 8 hours', 'route' => 'Oral'],
            ['name' => 'Cephalexin', 'dosage' => '500mg', 'frequency' => 'Twice daily', 'route' => 'Oral'],
        ];

        $procedures = [
            ['name' => 'Dental Cleaning', 'code' => 'DENT001'],
            ['name' => 'Wound Suturing', 'code' => 'SURG001'],
            ['name' => 'X-Ray Examination', 'code' => 'DIAG001'],
            ['name' => 'Blood Draw', 'code' => 'LAB001'],
            ['name' => 'Ear Cleaning', 'code' => 'PROC001'],
        ];

        $type = $this->faker->randomElement($treatmentTypes);
        $selectedMedication = $this->faker->randomElement($medications);
        $selectedProcedure = $this->faker->randomElement($procedures);

        $baseData = [
            'medical_record_id' => MedicalRecord::factory(),
            'pet_id' => Pet::factory(),
            'diagnosis_id' => $this->faker->optional(0.7)->passthrough(Diagnosis::factory()),
            'type' => $type,
            'start_date' => $this->faker->dateTimeBetween('-1 month', '+1 week')->format('Y-m-d'),
            'end_date' => $this->faker->optional(0.6)->dateTimeBetween('+1 week', '+3 months')?->format('Y-m-d'),
            'status' => $this->faker->randomElement(['prescribed', 'in_progress', 'completed', 'discontinued', 'on_hold']),
            'instructions' => $this->faker->optional(0.8)->sentence(),
            'response_notes' => $this->faker->optional(0.5)->sentence(),
            'cost' => $this->faker->optional(0.7)->randomFloat(2, 15.00, 500.00),
        ];

        // Type-specific data
        switch ($type) {
            case 'medication':
                return array_merge($baseData, [
                    'name' => $selectedMedication['name'],
                    'medication_name' => $selectedMedication['name'],
                    'dosage' => $selectedMedication['dosage'],
                    'frequency' => $selectedMedication['frequency'],
                    'route' => $selectedMedication['route'],
                    'duration_days' => $this->faker->numberBetween(3, 30),
                    'description' => "Medication: {$selectedMedication['name']} for treatment",
                ]);

            case 'procedure':
            case 'surgery':
                return array_merge($baseData, [
                    'name' => $selectedProcedure['name'],
                    'procedure_code' => $selectedProcedure['code'],
                    'procedure_notes' => $this->faker->optional(0.7)->sentence(),
                    'anesthesia_type' => $this->faker->randomElement(['none', 'local', 'general', 'sedation']),
                    'description' => "Procedure: {$selectedProcedure['name']}",
                ]);

            case 'vaccination':
                $vaccines = ['DHPP', 'Rabies', 'Bordetella', 'Lyme', 'Feline Distemper'];
                $vaccine = $this->faker->randomElement($vaccines);
                return array_merge($baseData, [
                    'name' => "{$vaccine} Vaccination",
                    'description' => "Vaccination against {$vaccine}",
                    'route' => 'Subcutaneous injection',
                ]);

            case 'diagnostic_test':
                $tests = ['Blood Chemistry Panel', 'Complete Blood Count', 'Urinalysis', 'Fecal Examination'];
                $test = $this->faker->randomElement($tests);
                return array_merge($baseData, [
                    'name' => $test,
                    'description' => "Diagnostic test: {$test}",
                ]);

            default:
                return array_merge($baseData, [
                    'name' => $this->faker->words(3, true),
                    'description' => $this->faker->sentence(),
                ]);
        }
    }

    /**
     * Create a treatment for a specific medical record.
     */
    public function forMedicalRecord(MedicalRecord $medicalRecord): static
    {
        return $this->state(fn (array $attributes) => [
            'medical_record_id' => $medicalRecord->id,
            'pet_id' => $medicalRecord->pet_id,
            'start_date' => $medicalRecord->visit_date,
        ]);
    }

    /**
     * Create a medication treatment.
     */
    public function medication(): static
    {
        $medications = [
            ['name' => 'Amoxicillin', 'dosage' => '250mg', 'frequency' => 'Twice daily', 'route' => 'Oral'],
            ['name' => 'Prednisone', 'dosage' => '5mg', 'frequency' => 'Once daily', 'route' => 'Oral'],
            ['name' => 'Metacam', 'dosage' => '1.5mg', 'frequency' => 'Once daily', 'route' => 'Oral'],
        ];
        
        $medication = $this->faker->randomElement($medications);
        
        return $this->state(fn (array $attributes) => [
            'type' => 'medication',
            'name' => $medication['name'],
            'medication_name' => $medication['name'],
            'dosage' => $medication['dosage'],
            'frequency' => $medication['frequency'],
            'route' => $medication['route'],
            'duration_days' => $this->faker->numberBetween(5, 14),
            'cost' => $this->faker->randomFloat(2, 25.00, 150.00),
        ]);
    }

    /**
     * Create a procedure treatment.
     */
    public function procedure(): static
    {
        $procedures = [
            ['name' => 'Dental Cleaning', 'code' => 'DENT001'],
            ['name' => 'Wound Suturing', 'code' => 'SURG001'],
            ['name' => 'X-Ray Examination', 'code' => 'DIAG001'],
        ];
        
        $procedure = $this->faker->randomElement($procedures);
        
        return $this->state(fn (array $attributes) => [
            'type' => 'procedure',
            'name' => $procedure['name'],
            'procedure_code' => $procedure['code'],
            'anesthesia_type' => $this->faker->randomElement(['local', 'general', 'sedation']),
            'cost' => $this->faker->randomFloat(2, 100.00, 800.00),
        ]);
    }

    /**
     * Create a surgery treatment.
     */
    public function surgery(): static
    {
        $surgeries = [
            'Spay Surgery',
            'Neuter Surgery',
            'Tumor Removal',
            'Fracture Repair',
            'Dental Extraction',
        ];
        
        return $this->state(fn (array $attributes) => [
            'type' => 'surgery',
            'name' => $this->faker->randomElement($surgeries),
            'anesthesia_type' => 'general',
            'cost' => $this->faker->randomFloat(2, 300.00, 2000.00),
        ]);
    }

    /**
     * Create a treatment with cost (for billing testing).
     */
    public function withCost(float $cost = null): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => $cost ?? $this->faker->randomFloat(2, 20.00, 500.00),
        ]);
    }

    /**
     * Create a treatment without cost (free treatment).
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => null,
        ]);
    }

    /**
     * Create a completed treatment.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'end_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Create an active treatment.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement(['prescribed', 'in_progress']),
            'end_date' => null,
        ]);
    }

    /**
     * Create a vaccination treatment.
     */
    public function vaccination(): static
    {
        $vaccines = ['DHPP', 'Rabies', 'Bordetella', 'Lyme Disease', 'Feline Distemper'];
        $vaccine = $this->faker->randomElement($vaccines);
        
        return $this->state(fn (array $attributes) => [
            'type' => 'vaccination',
            'name' => "{$vaccine} Vaccination",
            'description' => "Vaccination against {$vaccine}",
            'route' => 'Subcutaneous injection',
            'cost' => $this->faker->randomFloat(2, 35.00, 85.00),
        ]);
    }
} 