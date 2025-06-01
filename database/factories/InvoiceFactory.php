<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoiceDate = $this->faker->dateTimeBetween('-6 months', 'now');
        $dueDate = (clone $invoiceDate)->modify('+30 days');
        $serviceDate = $this->faker->dateTimeBetween($invoiceDate, 'now');
        
        $subtotal = $this->faker->randomFloat(2, 50.00, 800.00);
        $taxRate = $this->faker->randomElement([0, 5.0, 8.25, 10.0]);
        $taxAmount = $subtotal * ($taxRate / 100);
        
        // Discount (30% chance)
        $discountType = $this->faker->optional(0.3)->randomElement(['percentage', 'fixed']);
        $discountValue = 0;
        $discountAmount = 0;
        
        if ($discountType) {
            if ($discountType === 'percentage') {
                $discountValue = $this->faker->randomFloat(2, 5.0, 25.0);
                $discountAmount = $subtotal * ($discountValue / 100);
            } else {
                $discountValue = $this->faker->randomFloat(2, 10.00, 50.00);
                $discountAmount = min($discountValue, $subtotal * 0.5); // Max 50% discount
            }
        }
        
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        
        // Payment status and amounts
        $paymentStatus = $this->faker->randomElement(['unpaid', 'partially_paid', 'paid']);
        $paidAmount = 0;
        
        switch ($paymentStatus) {
            case 'paid':
                $paidAmount = $totalAmount;
                break;
            case 'partially_paid':
                $paidAmount = $this->faker->randomFloat(2, $totalAmount * 0.2, $totalAmount * 0.8);
                break;
            default: // unpaid
                $paidAmount = 0;
        }
        
        $balanceDue = $totalAmount - $paidAmount;
        
        // Determine status based on payment and due date
        $status = 'sent';
        if ($paymentStatus === 'paid') {
            $status = 'paid';
        } elseif ($dueDate < now() && $paidAmount == 0) {
            $status = 'overdue';
        } elseif ($paidAmount > 0 && $paidAmount < $totalAmount) {
            $status = 'partially_paid';
        }

        return [
            'invoice_number' => $this->generateInvoiceNumber(),
            'appointment_id' => $this->faker->optional(0.8)->passthrough(Appointment::factory()),
            'pet_id' => Pet::factory(),
            'owner_id' => User::factory(),
            'doctor_id' => Doctor::factory(),
            'invoice_date' => $invoiceDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'service_date' => $serviceDate->format('Y-m-d'),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
            'status' => $status,
            'notes' => $this->faker->optional(0.4)->sentence(),
            'sent_at' => $this->faker->optional(0.6)->dateTimeBetween($invoiceDate, 'now'),
            'viewed_at' => $this->faker->optional(0.4)->dateTimeBetween($invoiceDate, 'now'),
        ];
    }

    /**
     * Create an invoice for a specific appointment.
     */
    public function forAppointment(Appointment $appointment): static
    {
        return $this->state(fn (array $attributes) => [
            'appointment_id' => $appointment->id,
            'pet_id' => $appointment->pet_id,
            'owner_id' => $appointment->pet->owner_id,
            'doctor_id' => $appointment->doctor_id,
            'service_date' => $appointment->appointment_date,
        ]);
    }

    /**
     * Create a paid invoice.
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $totalAmount = $attributes['total_amount'] ?? $this->faker->randomFloat(2, 50.00, 500.00);
            
            return [
                'payment_status' => 'paid',
                'status' => 'paid',
                'paid_amount' => $totalAmount,
                'balance_due' => 0,
                'total_amount' => $totalAmount,
            ];
        });
    }

    /**
     * Create an unpaid invoice.
     */
    public function unpaid(): static
    {
        return $this->state(function (array $attributes) {
            $totalAmount = $attributes['total_amount'] ?? $this->faker->randomFloat(2, 50.00, 500.00);
            
            return [
                'payment_status' => 'unpaid',
                'status' => 'sent',
                'paid_amount' => 0,
                'balance_due' => $totalAmount,
                'total_amount' => $totalAmount,
            ];
        });
    }

    /**
     * Create an overdue invoice.
     */
    public function overdue(): static
    {
        return $this->state(function (array $attributes) {
            $totalAmount = $attributes['total_amount'] ?? $this->faker->randomFloat(2, 50.00, 500.00);
            $overdueDate = $this->faker->dateTimeBetween('-60 days', '-1 day');
            
            return [
                'payment_status' => 'unpaid',
                'status' => 'overdue',
                'paid_amount' => 0,
                'balance_due' => $totalAmount,
                'total_amount' => $totalAmount,
                'due_date' => $overdueDate->format('Y-m-d'),
                'sent_at' => $this->faker->dateTimeBetween('-90 days', $overdueDate),
            ];
        });
    }

    /**
     * Create a partially paid invoice.
     */
    public function partiallyPaid(): static
    {
        return $this->state(function (array $attributes) {
            $totalAmount = $attributes['total_amount'] ?? $this->faker->randomFloat(2, 100.00, 500.00);
            $paidAmount = $this->faker->randomFloat(2, $totalAmount * 0.3, $totalAmount * 0.8);
            
            return [
                'payment_status' => 'partially_paid',
                'status' => 'partially_paid',
                'paid_amount' => $paidAmount,
                'balance_due' => $totalAmount - $paidAmount,
                'total_amount' => $totalAmount,
            ];
        });
    }

    /**
     * Create a draft invoice.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'sent_at' => null,
            'viewed_at' => null,
        ]);
    }

    /**
     * Create an invoice with discount.
     */
    public function withDiscount(string $type = 'percentage', float $value = null): static
    {
        $discountValue = $value ?? ($type === 'percentage' ? 
            $this->faker->randomFloat(2, 5.0, 20.0) : 
            $this->faker->randomFloat(2, 10.00, 50.00)
        );
        
        return $this->state(function (array $attributes) use ($type, $discountValue) {
            $subtotal = $attributes['subtotal'] ?? $this->faker->randomFloat(2, 100.00, 500.00);
            $discountAmount = $type === 'percentage' ? 
                $subtotal * ($discountValue / 100) : 
                min($discountValue, $subtotal * 0.5);
            
            $taxAmount = $attributes['tax_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;
            
            return [
                'discount_type' => $type,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount - ($attributes['paid_amount'] ?? 0),
            ];
        });
    }

    /**
     * Create an invoice with tax.
     */
    public function withTax(float $taxRate = null): static
    {
        $rate = $taxRate ?? $this->faker->randomElement([5.0, 8.25, 10.0]);
        
        return $this->state(function (array $attributes) use ($rate) {
            $subtotal = $attributes['subtotal'] ?? $this->faker->randomFloat(2, 100.00, 500.00);
            $taxAmount = $subtotal * ($rate / 100);
            $discountAmount = $attributes['discount_amount'] ?? 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;
            
            return [
                'tax_rate' => $rate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount - ($attributes['paid_amount'] ?? 0),
            ];
        });
    }

    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $number = str_pad($this->faker->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return "INV-{$year}-{$month}-{$number}";
    }
} 