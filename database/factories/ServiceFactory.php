<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            // Consultation Services
            ['name' => 'General Consultation', 'category' => 'consultation', 'price' => 75.00, 'duration' => 30],
            ['name' => 'Emergency Consultation', 'category' => 'consultation', 'price' => 150.00, 'duration' => 45],
            ['name' => 'Follow-up Consultation', 'category' => 'consultation', 'price' => 50.00, 'duration' => 20],
            ['name' => 'Specialist Consultation', 'category' => 'consultation', 'price' => 125.00, 'duration' => 45],
            
            // Diagnostic Services
            ['name' => 'X-Ray Examination', 'category' => 'diagnostic', 'price' => 120.00, 'duration' => 30],
            ['name' => 'Blood Chemistry Panel', 'category' => 'diagnostic', 'price' => 85.00, 'duration' => 15],
            ['name' => 'Complete Blood Count', 'category' => 'diagnostic', 'price' => 65.00, 'duration' => 15],
            ['name' => 'Urinalysis', 'category' => 'diagnostic', 'price' => 45.00, 'duration' => 10],
            ['name' => 'Fecal Examination', 'category' => 'diagnostic', 'price' => 35.00, 'duration' => 10],
            ['name' => 'Ultrasound', 'category' => 'diagnostic', 'price' => 200.00, 'duration' => 45],
            
            // Treatment Services
            ['name' => 'Wound Treatment', 'category' => 'treatment', 'price' => 95.00, 'duration' => 30],
            ['name' => 'Ear Cleaning', 'category' => 'treatment', 'price' => 40.00, 'duration' => 15],
            ['name' => 'Nail Trimming', 'category' => 'treatment', 'price' => 25.00, 'duration' => 10],
            ['name' => 'Dental Cleaning', 'category' => 'treatment', 'price' => 350.00, 'duration' => 120],
            ['name' => 'Bandage Change', 'category' => 'treatment', 'price' => 30.00, 'duration' => 15],
            
            // Surgery Services
            ['name' => 'Spay Surgery', 'category' => 'surgery', 'price' => 450.00, 'duration' => 90],
            ['name' => 'Neuter Surgery', 'category' => 'surgery', 'price' => 350.00, 'duration' => 60],
            ['name' => 'Tumor Removal', 'category' => 'surgery', 'price' => 800.00, 'duration' => 120],
            ['name' => 'Dental Extraction', 'category' => 'surgery', 'price' => 250.00, 'duration' => 60],
            ['name' => 'Fracture Repair', 'category' => 'surgery', 'price' => 1200.00, 'duration' => 180],
            
            // Vaccination Services
            ['name' => 'DHPP Vaccination', 'category' => 'vaccination', 'price' => 45.00, 'duration' => 10],
            ['name' => 'Rabies Vaccination', 'category' => 'vaccination', 'price' => 35.00, 'duration' => 10],
            ['name' => 'Bordetella Vaccination', 'category' => 'vaccination', 'price' => 40.00, 'duration' => 10],
            ['name' => 'Feline Distemper', 'category' => 'vaccination', 'price' => 50.00, 'duration' => 10],
            
            // Grooming Services
            ['name' => 'Basic Grooming', 'category' => 'grooming', 'price' => 60.00, 'duration' => 60],
            ['name' => 'Full Grooming', 'category' => 'grooming', 'price' => 95.00, 'duration' => 90],
            ['name' => 'Bath Only', 'category' => 'grooming', 'price' => 35.00, 'duration' => 30],
        ];

        $service = $this->faker->randomElement($services);
        
        return [
            'name' => $service['name'],
            'description' => $this->faker->optional(0.8)->sentence(),
            'category' => $service['category'],
            'base_price' => $service['price'],
            'min_price' => null,
            'max_price' => null,
            'is_variable_pricing' => false,
            'estimated_duration' => $service['duration'],
            'service_code' => $this->generateServiceCode($service['category'], $service['name']),
            'is_active' => $this->faker->boolean(90), // 90% active
            'requires_appointment' => $this->faker->boolean(80), // 80% require appointment
            'required_equipment' => $this->faker->optional(0.3)->words(3),
            'tags' => $this->faker->optional(0.5)->words(2),
        ];
    }

    /**
     * Create a consultation service.
     */
    public function consultation(): static
    {
        $consultations = [
            ['name' => 'General Consultation', 'price' => 75.00],
            ['name' => 'Emergency Consultation', 'price' => 150.00],
            ['name' => 'Follow-up Consultation', 'price' => 50.00],
            ['name' => 'Specialist Consultation', 'price' => 125.00],
        ];
        
        $consultation = $this->faker->randomElement($consultations);
        
        return $this->state(fn (array $attributes) => [
            'name' => $consultation['name'],
            'category' => 'consultation',
            'base_price' => $consultation['price'],
            'estimated_duration' => $this->faker->numberBetween(20, 45),
            'requires_appointment' => true,
        ]);
    }

    /**
     * Create a diagnostic service.
     */
    public function diagnostic(): static
    {
        $diagnostics = [
            ['name' => 'X-Ray Examination', 'price' => 120.00],
            ['name' => 'Blood Chemistry Panel', 'price' => 85.00],
            ['name' => 'Ultrasound', 'price' => 200.00],
            ['name' => 'Urinalysis', 'price' => 45.00],
        ];
        
        $diagnostic = $this->faker->randomElement($diagnostics);
        
        return $this->state(fn (array $attributes) => [
            'name' => $diagnostic['name'],
            'category' => 'diagnostic',
            'base_price' => $diagnostic['price'],
            'estimated_duration' => $this->faker->numberBetween(15, 45),
        ]);
    }

    /**
     * Create a surgery service.
     */
    public function surgery(): static
    {
        $surgeries = [
            ['name' => 'Spay Surgery', 'price' => 450.00],
            ['name' => 'Neuter Surgery', 'price' => 350.00],
            ['name' => 'Tumor Removal', 'price' => 800.00],
            ['name' => 'Dental Extraction', 'price' => 250.00],
        ];
        
        $surgery = $this->faker->randomElement($surgeries);
        
        return $this->state(fn (array $attributes) => [
            'name' => $surgery['name'],
            'category' => 'surgery',
            'base_price' => $surgery['price'],
            'estimated_duration' => $this->faker->numberBetween(60, 180),
            'requires_appointment' => true,
        ]);
    }

    /**
     * Create a vaccination service.
     */
    public function vaccination(): static
    {
        $vaccines = [
            ['name' => 'DHPP Vaccination', 'price' => 45.00],
            ['name' => 'Rabies Vaccination', 'price' => 35.00],
            ['name' => 'Bordetella Vaccination', 'price' => 40.00],
            ['name' => 'Feline Distemper', 'price' => 50.00],
        ];
        
        $vaccine = $this->faker->randomElement($vaccines);
        
        return $this->state(fn (array $attributes) => [
            'name' => $vaccine['name'],
            'category' => 'vaccination',
            'base_price' => $vaccine['price'],
            'estimated_duration' => 10,
        ]);
    }

    /**
     * Create an emergency service.
     */
    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'emergency',
            'name' => 'Emergency ' . $this->faker->randomElement(['Consultation', 'Treatment', 'Surgery']),
            'base_price' => $this->faker->randomFloat(2, 150.00, 500.00),
            'is_emergency_service' => true,
            'requires_appointment' => false,
        ]);
    }

    /**
     * Create a variable pricing service.
     */
    public function variablePricing(): static
    {
        $basePrice = $this->faker->randomFloat(2, 50.00, 300.00);
        
        return $this->state(fn (array $attributes) => [
            'is_variable_pricing' => true,
            'base_price' => $basePrice,
            'min_price' => $basePrice * 0.8,
            'max_price' => $basePrice * 1.5,
        ]);
    }

    /**
     * Create an active service.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Create an inactive service.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Generate a service code.
     */
    private function generateServiceCode(string $category, string $name): string
    {
        $categoryCode = strtoupper(substr($category, 0, 3));
        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $number = str_pad($this->faker->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);
        
        return $categoryCode . '-' . $nameCode . '-' . $number;
    }
} 