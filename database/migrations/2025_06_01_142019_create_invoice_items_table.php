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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->nullable()->constrained()->onDelete('set null');
            
            // Item details
            $table->string('service_name'); // Store service name in case service is deleted
            $table->text('description')->nullable();
            $table->string('service_code')->nullable(); // Service code at time of invoice
            
            // Pricing and quantity
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2); // Price per unit at time of invoice
            $table->decimal('total_price', 10, 2); // quantity * unit_price
            
            // Discounts (item-level)
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('discount_reason')->nullable();
            
            // Additional information
            $table->text('notes')->nullable(); // Notes specific to this item
            $table->json('metadata')->nullable(); // Additional data (e.g., equipment used)
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index(['invoice_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
