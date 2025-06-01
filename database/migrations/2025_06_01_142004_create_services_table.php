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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            
            // Service information
            $table->string('name'); // e.g., "General Checkup", "X-Ray", "Surgery"
            $table->text('description')->nullable();
            $table->enum('category', [
                'consultation',
                'diagnostic',
                'treatment',
                'surgery',
                'vaccination',
                'grooming',
                'emergency',
                'other'
            ]);
            
            // Pricing
            $table->decimal('base_price', 10, 2); // Base price for the service
            $table->decimal('min_price', 10, 2)->nullable(); // Minimum price (for variable pricing)
            $table->decimal('max_price', 10, 2)->nullable(); // Maximum price (for variable pricing)
            $table->boolean('is_variable_pricing')->default(false); // If price can vary
            
            // Service details
            $table->integer('estimated_duration')->nullable(); // Duration in minutes
            $table->text('notes')->nullable(); // Additional notes about the service
            $table->json('required_equipment')->nullable(); // Equipment needed for service
            
            // Status and availability
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_appointment')->default(true);
            $table->boolean('is_emergency_service')->default(false);
            
            // Metadata
            $table->string('service_code')->unique()->nullable(); // Internal service code
            $table->json('tags')->nullable(); // Tags for categorization and search
            
            $table->timestamps();
            
            // Indexes
            $table->index(['category', 'is_active']);
            $table->index('service_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
