<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $services = [
            ['name' => 'General Consultation', 'code' => 'CON-GEN-001', 'price' => 75.00],
            ['name' => 'X-Ray Examination', 'code' => 'DIA-XRA-001', 'price' => 120.00],
            ['name' => 'Blood Chemistry Panel', 'code' => 'DIA-BLO-001', 'price' => 85.00],
            ['name' => 'Vaccination - DHPP', 'code' => 'VAC-DHP-001', 'price' => 45.00],
            ['name' => 'Dental Cleaning', 'code' => 'TRE-DEN-001', 'price' => 350.00],
            ['name' => 'Spay Surgery', 'code' => 'SUR-SPA-001', 'price' => 450.00],
            ['name' => 'Medication - Amoxicillin', 'code' => 'MED-AMO-001', 'price' => 35.00],
            ['name' => 'Wound Treatment', 'code' => 'TRE-WOU-001', 'price' => 95.00],
        ];

        $service = $this->faker->randomElement($services);
        $quantity = $this->faker->numberBetween(1, 3);
        $unitPrice = $service['price'];
        $totalPrice = $quantity * $unitPrice;

        return [
            'invoice_id' => Invoice::factory(),
            'service_id' => $this->faker->optional(0.8)->passthrough(Service::factory()),
            'service_name' => $service['name'],
            'description' => $this->faker->optional(0.6)->sentence(),
            'service_code' => $service['code'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'metadata' => $this->faker->optional(0.4)->passthrough([
                'category' => $this->faker->randomElement(['consultation', 'diagnostic', 'treatment', 'surgery']),
                'auto_generated' => $this->faker->boolean(30),
            ]),
        ];
    }

    /**
     * Create an invoice item for a specific invoice.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
        ]);
    }

    /**
     * Create an invoice item for a specific service.
     */
    public function forService(Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $service->id,
            'service_name' => $service->name,
            'service_code' => $service->service_code,
            'unit_price' => $service->base_price,
            'total_price' => $service->base_price * ($attributes['quantity'] ?? 1),
            'description' => $service->description,
        ]);
    }

    /**
     * Create a consultation item.
     */
    public function consultation(): static
    {
        $consultations = [
            ['name' => 'General Consultation', 'code' => 'CON-GEN-001', 'price' => 75.00],
            ['name' => 'Emergency Consultation', 'code' => 'CON-EME-001', 'price' => 150.00],
            ['name' => 'Follow-up Consultation', 'code' => 'CON-FOL-001', 'price' => 50.00],
            ['name' => 'Specialist Consultation', 'code' => 'CON-SPE-001', 'price' => 125.00],
        ];

        $consultation = $this->faker->randomElement($consultations);

        return $this->state(fn (array $attributes) => [
            'service_name' => $consultation['name'],
            'service_code' => $consultation['code'],
            'unit_price' => $consultation['price'],
            'total_price' => $consultation['price'],
            'quantity' => 1,
            'description' => 'Professional veterinary consultation',
        ]);
    }

    /**
     * Create a medication item.
     */
    public function medication(): static
    {
        $medications = [
            ['name' => 'Amoxicillin 250mg', 'code' => 'MED-AMO-001', 'price' => 35.00],
            ['name' => 'Prednisone 5mg', 'code' => 'MED-PRE-001', 'price' => 25.00],
            ['name' => 'Metacam 1.5mg', 'code' => 'MED-MET-001', 'price' => 45.00],
            ['name' => 'Tramadol 50mg', 'code' => 'MED-TRA-001', 'price' => 40.00],
        ];

        $medication = $this->faker->randomElement($medications);

        return $this->state(fn (array $attributes) => [
            'service_name' => $medication['name'],
            'service_code' => $medication['code'],
            'unit_price' => $medication['price'],
            'total_price' => $medication['price'],
            'quantity' => 1,
            'description' => 'Prescription medication',
            'metadata' => [
                'category' => 'medication',
                'auto_generated' => true,
                'treatment_id' => $this->faker->optional(0.8)->numberBetween(1, 100),
            ],
        ]);
    }

    /**
     * Create a surgery item.
     */
    public function surgery(): static
    {
        $surgeries = [
            ['name' => 'Spay Surgery', 'code' => 'SUR-SPA-001', 'price' => 450.00],
            ['name' => 'Neuter Surgery', 'code' => 'SUR-NEU-001', 'price' => 350.00],
            ['name' => 'Tumor Removal', 'code' => 'SUR-TUM-001', 'price' => 800.00],
            ['name' => 'Dental Extraction', 'code' => 'SUR-DEN-001', 'price' => 250.00],
        ];

        $surgery = $this->faker->randomElement($surgeries);

        return $this->state(fn (array $attributes) => [
            'service_name' => $surgery['name'],
            'service_code' => $surgery['code'],
            'unit_price' => $surgery['price'],
            'total_price' => $surgery['price'],
            'quantity' => 1,
            'description' => 'Surgical procedure',
        ]);
    }

    /**
     * Create a diagnostic item.
     */
    public function diagnostic(): static
    {
        $diagnostics = [
            ['name' => 'X-Ray Examination', 'code' => 'DIA-XRA-001', 'price' => 120.00],
            ['name' => 'Blood Chemistry Panel', 'code' => 'DIA-BLO-001', 'price' => 85.00],
            ['name' => 'Complete Blood Count', 'code' => 'DIA-CBC-001', 'price' => 65.00],
            ['name' => 'Urinalysis', 'code' => 'DIA-URI-001', 'price' => 45.00],
            ['name' => 'Ultrasound', 'code' => 'DIA-ULT-001', 'price' => 200.00],
        ];

        $diagnostic = $this->faker->randomElement($diagnostics);

        return $this->state(fn (array $attributes) => [
            'service_name' => $diagnostic['name'],
            'service_code' => $diagnostic['code'],
            'unit_price' => $diagnostic['price'],
            'total_price' => $diagnostic['price'],
            'quantity' => 1,
            'description' => 'Diagnostic test',
        ]);
    }

    /**
     * Create a vaccination item.
     */
    public function vaccination(): static
    {
        $vaccines = [
            ['name' => 'DHPP Vaccination', 'code' => 'VAC-DHP-001', 'price' => 45.00],
            ['name' => 'Rabies Vaccination', 'code' => 'VAC-RAB-001', 'price' => 35.00],
            ['name' => 'Bordetella Vaccination', 'code' => 'VAC-BOR-001', 'price' => 40.00],
            ['name' => 'Feline Distemper', 'code' => 'VAC-FEL-001', 'price' => 50.00],
        ];

        $vaccine = $this->faker->randomElement($vaccines);

        return $this->state(fn (array $attributes) => [
            'service_name' => $vaccine['name'],
            'service_code' => $vaccine['code'],
            'unit_price' => $vaccine['price'],
            'total_price' => $vaccine['price'],
            'quantity' => 1,
            'description' => 'Vaccination service',
        ]);
    }

    /**
     * Create an auto-generated item (from treatment).
     */
    public function autoGenerated(int $treatmentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => [
                'auto_generated' => true,
                'treatment_id' => $treatmentId ?? $this->faker->numberBetween(1, 100),
                'category' => 'treatment',
            ],
            'notes' => 'Auto-generated from treatment',
        ]);
    }

    /**
     * Create an item with custom quantity.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitPrice = $attributes['unit_price'] ?? $this->faker->randomFloat(2, 20.00, 200.00);
            
            return [
                'quantity' => $quantity,
                'total_price' => $unitPrice * $quantity,
            ];
        });
    }

    /**
     * Create an item with custom price.
     */
    public function withPrice(float $unitPrice): static
    {
        return $this->state(function (array $attributes) use ($unitPrice) {
            $quantity = $attributes['quantity'] ?? 1;
            
            return [
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $quantity,
            ];
        });
    }
} 