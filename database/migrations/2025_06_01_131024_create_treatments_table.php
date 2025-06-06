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
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->foreignId('diagnosis_id')->nullable()->constrained()->onDelete('set null');
            
            // Treatment information
            $table->enum('type', ['medication', 'procedure', 'surgery', 'therapy', 'vaccination', 'diagnostic_test', 'other']);
            $table->string('name');
            $table->text('description')->nullable();
            
            // Medication specific fields
            $table->string('medication_name')->nullable();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable(); // e.g., "twice daily", "every 8 hours"
            $table->string('route')->nullable(); // oral, injection, topical, etc.
            $table->integer('duration_days')->nullable();
            
            // Procedure/Surgery specific fields
            $table->string('procedure_code')->nullable();
            $table->text('procedure_notes')->nullable();
            $table->enum('anesthesia_type', ['none', 'local', 'general', 'sedation'])->nullable();
            
            // Timeline
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->datetime('administered_at')->nullable();
            
            // Status and monitoring
            $table->enum('status', ['prescribed', 'in_progress', 'completed', 'discontinued', 'on_hold'])->default('prescribed');
            $table->text('instructions')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('response_notes')->nullable();
            
            // Cost and billing
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('billing_code')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['pet_id', 'start_date']);
            $table->index(['medical_record_id']);
            $table->index(['type', 'status']);
            $table->index(['medication_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
