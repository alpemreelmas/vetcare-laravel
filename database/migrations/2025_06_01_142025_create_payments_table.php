<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Who made the payment
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null'); // Staff who processed
            
            // Payment identification
            $table->string('payment_number')->unique(); // e.g., "PAY-2024-001"
            $table->string('transaction_id')->nullable(); // External transaction ID
            $table->string('reference_number')->nullable(); // Bank reference or check number
            
            // Payment details
            $table->decimal('amount', 10, 2); // Payment amount
            $table->enum('payment_method', [
                'cash',
                'credit_card',
                'debit_card',
                'bank_transfer',
                'online_payment',
                'check',
                'mobile_payment',
                'insurance',
                'other'
            ]);
            
            // Payment status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'cancelled',
                'refunded',
                'disputed'
            ])->default('pending');
            
            // Dates
            $table->timestamp('payment_date'); // When payment was made
            $table->timestamp('processed_at')->nullable(); // When payment was processed
            $table->timestamp('cleared_at')->nullable(); // When payment cleared (for checks/transfers)
            
            // Payment method specific details
            $table->string('card_last_four')->nullable(); // Last 4 digits of card
            $table->string('card_type')->nullable(); // Visa, MasterCard, etc.
            $table->string('bank_name')->nullable(); // For bank transfers
            $table->string('check_number')->nullable(); // For check payments
            $table->json('gateway_response')->nullable(); // Payment gateway response data
            
            // Additional information
            $table->text('notes')->nullable(); // Payment notes
            $table->text('failure_reason')->nullable(); // Why payment failed
            $table->decimal('fee_amount', 10, 2)->default(0); // Processing fees
            $table->string('currency', 3)->default('USD'); // Currency code
            
            // Refund information
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['invoice_id', 'status']);
            $table->index(['user_id', 'payment_date']);
            $table->index(['payment_method', 'status']);
            $table->index('payment_number');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
