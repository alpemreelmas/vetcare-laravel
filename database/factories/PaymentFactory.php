<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentMethods = [
            'cash', 'credit_card', 'debit_card', 'bank_transfer', 
            'online_payment', 'check', 'mobile_payment', 'insurance'
        ];

        $amount = $this->faker->randomFloat(2, 25.00, 1000.00);
        $feeAmount = $this->faker->randomFloat(2, 0, $amount * 0.03); // Up to 3% processing fee

        $paymentMethod = $this->faker->randomElement($paymentMethods);
        $status = $this->faker->randomElement(['pending', 'completed', 'failed', 'cancelled', 'refunded']);

        return [
            'invoice_id' => Invoice::factory(),
            'user_id' => \App\Models\User::factory(),
            'payment_number' => $this->generatePaymentNumber(),
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'payment_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'transaction_id' => $this->generateTransactionId($paymentMethod),
            'gateway_response' => $this->generateGatewayResponse($paymentMethod, $status),
            'fee_amount' => $feeAmount,
            'status' => $status,
            'reference_number' => $this->faker->optional(0.7)->regexify('[A-Z0-9]{8,12}'),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'refunded_amount' => $status === 'refunded' ? $this->faker->randomFloat(2, 0, $amount) : 0,
            'refunded_at' => $status === 'refunded' ? $this->faker->dateTimeBetween('-3 months', 'now') : null,
            'refund_reason' => $status === 'refunded' ? $this->faker->randomElement([
                'Customer request', 'Service not provided', 'Billing error', 'Duplicate payment'
            ]) : null,
        ];
    }

    /**
     * Create a payment for a specific invoice.
     */
    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->owner_id,
            'amount' => $this->faker->randomFloat(2, $invoice->balance_due * 0.5, $invoice->balance_due),
        ]);
    }

    /**
     * Create a completed payment.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'transaction_id' => $this->generateTransactionId($attributes['payment_method'] ?? 'credit_card'),
            'gateway_response' => $this->generateGatewayResponse($attributes['payment_method'] ?? 'credit_card', 'completed'),
        ]);
    }

    /**
     * Create a pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'transaction_id' => null,
            'gateway_response' => null,
        ]);
    }

    /**
     * Create a failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'gateway_response' => [
                'error_code' => $this->faker->randomElement(['declined', 'insufficient_funds', 'expired_card', 'invalid_card']),
                'error_message' => $this->faker->randomElement([
                    'Card declined by issuer',
                    'Insufficient funds',
                    'Card has expired',
                    'Invalid card number'
                ]),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Create a refunded payment.
     */
    public function refunded(float $refundAmount = null, string $reason = null): static
    {
        return $this->state(function (array $attributes) use ($refundAmount, $reason) {
            $originalAmount = $attributes['amount'] ?? $this->faker->randomFloat(2, 50.00, 500.00);
            $refund = $refundAmount ?? $this->faker->randomFloat(2, $originalAmount * 0.5, $originalAmount);
            
            return [
                'status' => 'refunded',
                'refunded_amount' => $refund,
                'refunded_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
                'refund_reason' => $reason ?? $this->faker->randomElement([
                    'Customer request',
                    'Service not provided',
                    'Billing error',
                    'Duplicate payment'
                ]),
            ];
        });
    }

    /**
     * Create a cash payment.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
            'fee_amount' => 0,
            'status' => 'completed',
        ]);
    }

    /**
     * Create a credit card payment.
     */
    public function creditCard(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? $this->faker->randomFloat(2, 25.00, 1000.00);
            $feeAmount = $amount * 0.029; // 2.9% processing fee
            
            return [
                'payment_method' => 'credit_card',
                'fee_amount' => $feeAmount,
                'status' => 'succeeded',
                'transaction_id' => 'cc_' . $this->faker->regexify('[0-9a-f]{24}'),
                'gateway_response' => [
                    'gateway' => 'stripe',
                    'charge_id' => 'ch_' . $this->faker->regexify('[0-9a-zA-Z]{24}'),
                    'card_last4' => $this->faker->numerify('####'),
                    'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']),
                ],
            ];
        });
    }

    /**
     * Create an online payment.
     */
    public function online(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? $this->faker->randomFloat(2, 25.00, 1000.00);
            $feeAmount = $amount * 0.025; // 2.5% processing fee
            
            return [
                'payment_method' => 'online_payment',
                'fee_amount' => $feeAmount,
                'status' => 'completed',
                'transaction_id' => 'pay_' . $this->faker->regexify('[0-9a-f]{24}'),
                'gateway_response' => [
                    'gateway' => $this->faker->randomElement(['paypal', 'square', 'stripe']),
                    'transaction_id' => $this->faker->regexify('[0-9A-Z]{16}'),
                ],
            ];
        });
    }

    /**
     * Create a bank transfer payment.
     */
    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'bank_transfer',
            'fee_amount' => 0,
            'status' => 'completed',
            'transaction_id' => 'bt_' . $this->faker->regexify('[0-9]{12}'),
            'reference_number' => $this->faker->regexify('[A-Z0-9]{10}'),
            'gateway_response' => [
                'bank_name' => $this->faker->company() . ' Bank',
                'account_last4' => $this->faker->numerify('####'),
                'routing_number' => $this->faker->numerify('#########'),
            ],
        ]);
    }

    /**
     * Create an insurance payment.
     */
    public function insurance(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'insurance',
            'fee_amount' => 0,
            'status' => 'completed',
            'reference_number' => $this->faker->regexify('[A-Z]{3}[0-9]{8}'),
            'notes' => 'Insurance claim payment - Policy #' . $this->faker->regexify('[A-Z0-9]{10}'),
            'gateway_response' => [
                'insurance_company' => $this->faker->randomElement([
                    'VetCare Insurance', 'Pet Health Plus', 'Animal Wellness Co', 'PetGuard Insurance'
                ]),
                'claim_number' => $this->faker->regexify('[0-9]{10}'),
                'policy_number' => $this->faker->regexify('[A-Z0-9]{12}'),
                'coverage_percentage' => $this->faker->randomElement([70, 80, 90, 100]),
            ],
        ]);
    }

    /**
     * Create a check payment.
     */
    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'check',
            'fee_amount' => 0,
            'status' => 'completed',
            'reference_number' => $this->faker->numerify('####'),
            'notes' => 'Check #' . $this->faker->numerify('####'),
            'gateway_response' => [
                'check_number' => $this->faker->numerify('####'),
                'bank_name' => $this->faker->company() . ' Bank',
                'account_last4' => $this->faker->numerify('####'),
            ],
        ]);
    }

    /**
     * Generate a unique payment number.
     */
    private function generatePaymentNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $number = str_pad($this->faker->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return "PAY-{$year}-{$month}-{$number}";
    }

    /**
     * Generate a transaction ID based on payment method.
     */
    private function generateTransactionId(string $paymentMethod): ?string
    {
        return match($paymentMethod) {
            'cash' => null,
            'credit_card' => 'cc_' . $this->faker->regexify('[0-9a-f]{24}'),
            'debit_card' => 'dc_' . $this->faker->regexify('[0-9a-f]{24}'),
            'bank_transfer' => 'bt_' . $this->faker->regexify('[0-9]{12}'),
            'online_payment' => 'pay_' . $this->faker->regexify('[0-9a-f]{24}'),
            'check' => 'chk_' . $this->faker->numerify('########'),
            'mobile_payment' => 'mob_' . $this->faker->regexify('[0-9a-f]{20}'),
            'insurance' => 'ins_' . $this->faker->regexify('[A-Z0-9]{16}'),
            default => $this->faker->regexify('[0-9a-f]{24}'),
        };
    }

    /**
     * Generate gateway response based on payment method and status.
     */
    private function generateGatewayResponse(string $paymentMethod, string $status): ?array
    {
        if ($paymentMethod === 'cash') {
            return null;
        }

        $baseResponse = [
            'timestamp' => now()->toISOString(),
            'status' => $status,
        ];

        return match($paymentMethod) {
            'credit_card', 'debit_card' => array_merge($baseResponse, [
                'gateway' => 'stripe',
                'charge_id' => 'ch_' . $this->faker->regexify('[0-9a-zA-Z]{24}'),
                'card_last4' => $this->faker->numerify('####'),
                'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex']),
            ]),
            'online_payment' => array_merge($baseResponse, [
                'gateway' => $this->faker->randomElement(['paypal', 'square', 'stripe']),
                'transaction_id' => $this->faker->regexify('[0-9A-Z]{16}'),
            ]),
            'bank_transfer' => array_merge($baseResponse, [
                'bank_name' => $this->faker->company() . ' Bank',
                'account_last4' => $this->faker->numerify('####'),
            ]),
            'insurance' => array_merge($baseResponse, [
                'insurance_company' => $this->faker->randomElement([
                    'VetCare Insurance', 'Pet Health Plus', 'Animal Wellness Co'
                ]),
                'claim_number' => $this->faker->regexify('[0-9]{10}'),
            ]),
            default => $baseResponse,
        };
    }
} 