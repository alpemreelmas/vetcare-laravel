<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MedicalDocument;
use App\Models\MedicalRecord;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Seeder;

class MedicalDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing data
        $users = User::role('user')->get();
        $doctors = Doctor::all();
        $pets = Pet::all();
        $services = Service::all();

        if ($users->isEmpty() || $doctors->isEmpty() || $pets->isEmpty()) {
            $this->command->warn('Please run UserSeeder, DoctorSeeder, PetSeeder, and ServiceSeeder first.');
            return;
        }

        $this->command->info('Creating medical scenarios...');

        // Create 50 complete medical scenarios
        for ($i = 0; $i < 50; $i++) {
            $this->createMedicalScenario($users, $doctors, $pets, $services);
        }

        $this->command->info('Medical data seeding completed!');
    }

    /**
     * Create a complete medical scenario with appointment, medical record, diagnosis, treatments, and billing.
     */
    private function createMedicalScenario($users, $doctors, $pets, $services): void
    {
        $pet = $pets->random();
        $doctor = $doctors->random();
        $owner = $pet->owner;

        // Create appointment
        $appointmentDate = fake()->dateTimeBetween('-6 months', '-1 day');
        $appointmentTime = fake()->time('H:i:s');
        $startDateTime = $appointmentDate->format('Y-m-d') . ' ' . $appointmentTime;
        $duration = fake()->numberBetween(20, 60); // minutes
        $endDateTime = (clone $appointmentDate)->modify("+{$duration} minutes");

        $appointment = Appointment::factory()
            ->for($pet)
            ->for($doctor)
            ->create([
                'status' => 'completed',
                'start_datetime' => $startDateTime,
                'end_datetime' => $endDateTime->format('Y-m-d H:i:s'),
                'duration' => $duration,
            ]);

        // Create medical record
        $medicalRecord = MedicalRecord::factory()
            ->forAppointment($appointment)
            ->completed()
            ->create();

        // Create 1-3 diagnoses
        $diagnosisCount = fake()->numberBetween(1, 3);
        $diagnoses = [];
        
        for ($d = 0; $d < $diagnosisCount; $d++) {
            $diagnosis = Diagnosis::factory()
                ->forMedicalRecord($medicalRecord)
                ->create();
            $diagnoses[] = $diagnosis;
        }

        // Create treatments for each diagnosis
        $treatments = [];
        foreach ($diagnoses as $diagnosis) {
            $treatmentCount = fake()->numberBetween(1, 4);
            
            for ($t = 0; $t < $treatmentCount; $t++) {
                $treatment = Treatment::factory()
                    ->forMedicalRecord($medicalRecord)
                    ->create([
                        'diagnosis_id' => $diagnosis->id,
                        'cost' => fake()->optional(0.8)->randomFloat(2, 25.00, 300.00), // 80% have cost
                    ]);
                $treatments[] = $treatment;
            }
        }

        // Create medical documents
        $documentCount = fake()->numberBetween(0, 5);
        for ($doc = 0; $doc < $documentCount; $doc++) {
            MedicalDocument::factory()
                ->forMedicalRecord($medicalRecord)
                ->create();
        }

        // Create invoice if there are treatments with costs
        $treatmentsWithCost = collect($treatments)->filter(fn($t) => $t->cost > 0);
        
        if ($treatmentsWithCost->isNotEmpty()) {
            $this->createInvoiceScenario($appointment, $treatmentsWithCost, $services);
        }

        // Sometimes create additional services invoice (consultation, diagnostics, etc.)
        if (fake()->boolean(70)) { // 70% chance
            $this->createServicesInvoice($appointment, $services);
        }
    }

    /**
     * Create an invoice scenario with treatments and payments.
     */
    private function createInvoiceScenario($appointment, $treatments, $services): void
    {
        $pet = $appointment->pet;
        $owner = $pet->owner;
        $doctor = $appointment->doctor;

        // Create invoice
        $invoice = Invoice::factory()
            ->forAppointment($appointment)
            ->create([
                'status' => 'sent',
                'payment_status' => 'unpaid',
            ]);

        $totalAmount = 0;

        // Add consultation service
        $consultationService = $services->where('category', 'consultation')->first();
        if ($consultationService) {
            InvoiceItem::factory()
                ->forInvoice($invoice)
                ->forService($consultationService)
                ->create();
            $totalAmount += $consultationService->base_price;
        }

        // Add treatment items
        foreach ($treatments as $treatment) {
            if ($treatment->cost > 0) {
                $invoiceItem = InvoiceItem::factory()
                    ->forInvoice($invoice)
                    ->create([
                        'service_name' => $treatment->name,
                        'description' => $treatment->description,
                        'unit_price' => $treatment->cost,
                        'total_price' => $treatment->cost,
                        'quantity' => 1,
                        'metadata' => [
                            'treatment_id' => $treatment->id,
                            'auto_generated' => true,
                            'category' => 'treatment',
                        ],
                    ]);
                $totalAmount += $treatment->cost;
            }
        }

        // Update invoice totals
        $taxRate = fake()->randomElement([0, 5.0, 8.25]);
        $taxAmount = $totalAmount * ($taxRate / 100);
        $finalTotal = $totalAmount + $taxAmount;

        $invoice->update([
            'subtotal' => $totalAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $finalTotal,
            'balance_due' => $finalTotal,
        ]);

        // Create payments (70% chance of payment)
        if (fake()->boolean(70)) {
            $this->createPaymentScenario($invoice);
        }
    }

    /**
     * Create a services-only invoice (diagnostics, procedures, etc.).
     */
    private function createServicesInvoice($appointment, $services): void
    {
        $invoice = Invoice::factory()
            ->forAppointment($appointment)
            ->create();

        $totalAmount = 0;
        $serviceCount = fake()->numberBetween(1, 4);

        // Add random services
        $selectedServices = $services->random($serviceCount);
        
        foreach ($selectedServices as $service) {
            $quantity = fake()->numberBetween(1, 2);
            $unitPrice = $service->base_price;
            
            // Apply variable pricing if applicable
            if ($service->is_variable_pricing && $service->min_price && $service->max_price) {
                $unitPrice = fake()->randomFloat(2, $service->min_price, $service->max_price);
            }
            
            $itemTotal = $unitPrice * $quantity;
            
            InvoiceItem::factory()
                ->forInvoice($invoice)
                ->forService($service)
                ->withQuantity($quantity)
                ->withPrice($unitPrice)
                ->create();
                
            $totalAmount += $itemTotal;
        }

        // Update invoice totals
        $taxRate = fake()->randomElement([0, 5.0, 8.25]);
        $taxAmount = $totalAmount * ($taxRate / 100);
        
        // Apply discount (30% chance)
        $discountAmount = 0;
        $discountType = null;
        $discountValue = 0;
        
        if (fake()->boolean(30)) {
            $discountType = fake()->randomElement(['percentage', 'fixed']);
            if ($discountType === 'percentage') {
                $discountValue = fake()->randomFloat(2, 5.0, 20.0);
                $discountAmount = $totalAmount * ($discountValue / 100);
            } else {
                $discountValue = fake()->randomFloat(2, 10.00, min(50.00, $totalAmount * 0.3));
                $discountAmount = $discountValue;
            }
        }
        
        $finalTotal = $totalAmount + $taxAmount - $discountAmount;

        $invoice->update([
            'subtotal' => $totalAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'total_amount' => $finalTotal,
            'balance_due' => $finalTotal,
            'status' => 'sent',
            'payment_status' => 'unpaid',
        ]);

        // Create payments (60% chance for services)
        if (fake()->boolean(60)) {
            $this->createPaymentScenario($invoice);
        }
    }

    /**
     * Create payment scenario for an invoice.
     */
    private function createPaymentScenario($invoice): void
    {
        $paymentScenario = fake()->randomElement(['full', 'partial', 'multiple']);
        
        switch ($paymentScenario) {
            case 'full':
                // Single full payment
                $payment = Payment::factory()
                    ->forInvoice($invoice)
                    ->completed()
                    ->create([
                        'amount' => $invoice->total_amount,
                        'payment_date' => fake()->dateTimeBetween($invoice->invoice_date, 'now')->format('Y-m-d'),
                    ]);
                
                $invoice->update([
                    'paid_amount' => $invoice->total_amount,
                    'balance_due' => 0,
                    'payment_status' => 'paid',
                    'status' => 'paid',
                ]);
                break;
                
            case 'partial':
                // Partial payment
                $partialAmount = fake()->randomFloat(2, $invoice->total_amount * 0.3, $invoice->total_amount * 0.8);
                
                $payment = Payment::factory()
                    ->forInvoice($invoice)
                    ->completed()
                    ->create([
                        'amount' => $partialAmount,
                        'payment_date' => fake()->dateTimeBetween($invoice->invoice_date, 'now')->format('Y-m-d'),
                    ]);
                
                $invoice->update([
                    'paid_amount' => $partialAmount,
                    'balance_due' => $invoice->total_amount - $partialAmount,
                    'payment_status' => 'partially_paid',
                ]);
                break;
                
            case 'multiple':
                // Multiple payments
                $remainingAmount = $invoice->total_amount;
                $paymentCount = fake()->numberBetween(2, 4);
                $totalPaid = 0;
                
                for ($p = 0; $p < $paymentCount && $remainingAmount > 10; $p++) {
                    $isLastPayment = ($p === $paymentCount - 1);
                    
                    if ($isLastPayment) {
                        $paymentAmount = $remainingAmount;
                    } else {
                        $maxPayment = min($remainingAmount * 0.7, $remainingAmount - 10);
                        $paymentAmount = fake()->randomFloat(2, 20.00, $maxPayment);
                    }
                    
                    Payment::factory()
                        ->forInvoice($invoice)
                        ->completed()
                        ->create([
                            'amount' => $paymentAmount,
                            'payment_date' => fake()->dateTimeBetween($invoice->invoice_date, 'now')->format('Y-m-d'),
                        ]);
                    
                    $totalPaid += $paymentAmount;
                    $remainingAmount -= $paymentAmount;
                }
                
                $finalBalance = $invoice->total_amount - $totalPaid;
                $paymentStatus = $finalBalance <= 0.01 ? 'paid' : 'partially_paid';
                
                $invoice->update([
                    'paid_amount' => $totalPaid,
                    'balance_due' => max(0, $finalBalance),
                    'payment_status' => $paymentStatus,
                    'status' => $paymentStatus === 'paid' ? 'paid' : 'sent',
                ]);
                break;
        }
    }
} 