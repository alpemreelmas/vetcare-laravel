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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            
            // Invoice identification
            $table->string('invoice_number')->unique(); // e.g., "INV-2024-001"
            $table->foreignId('appointment_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('doctor_id')->nullable()->constrained()->onDelete('set null');
            
            // Invoice dates
            $table->date('invoice_date');
            $table->date('due_date');
            $table->date('service_date')->nullable(); // Date when services were provided
            
            // Financial information
            $table->decimal('subtotal', 10, 2); // Total before tax and discounts
            $table->decimal('tax_rate', 5, 2)->default(0); // Tax percentage
            $table->decimal('tax_amount', 10, 2)->default(0); // Calculated tax amount
            $table->decimal('discount_amount', 10, 2)->default(0); // Total discount
            $table->decimal('total_amount', 10, 2); // Final amount to pay
            $table->decimal('paid_amount', 10, 2)->default(0); // Amount already paid
            $table->decimal('balance_due', 10, 2); // Remaining balance
            
            // Invoice status
            $table->enum('status', [
                'draft',
                'sent',
                'viewed',
                'paid',
                'partially_paid',
                'overdue',
                'cancelled',
                'refunded'
            ])->default('draft');
            
            // Payment information
            $table->enum('payment_status', [
                'unpaid',
                'partially_paid',
                'paid',
                'refunded'
            ])->default('unpaid');
            
            // Additional information
            $table->text('notes')->nullable(); // Internal notes
            $table->text('terms_and_conditions')->nullable(); // Invoice terms
            $table->text('payment_instructions')->nullable(); // How to pay
            
            // Discount information
            $table->string('discount_type')->nullable(); // 'percentage' or 'fixed'
            $table->decimal('discount_value', 10, 2)->nullable(); // Discount value
            $table->string('discount_reason')->nullable(); // Reason for discount
            
            // Email tracking
            $table->timestamp('sent_at')->nullable(); // When invoice was sent
            $table->timestamp('viewed_at')->nullable(); // When invoice was first viewed
            $table->timestamp('paid_at')->nullable(); // When invoice was fully paid
            
            $table->timestamps();
            
            // Indexes
            $table->index(['owner_id', 'status']);
            $table->index(['doctor_id', 'invoice_date']);
            $table->index(['status', 'due_date']);
            $table->index('invoice_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
