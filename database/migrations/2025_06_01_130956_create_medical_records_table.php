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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->onDelete('cascade');
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->foreignId('doctor_id')->constrained()->onDelete('cascade');
            
            // Visit information
            $table->date('visit_date');
            $table->text('chief_complaint')->nullable(); // Main reason for visit
            $table->text('history_of_present_illness')->nullable(); // Current problem details
            $table->text('physical_examination')->nullable(); // Physical exam findings
            
            // Vital signs
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable(); // in Celsius
            $table->integer('heart_rate')->nullable(); // beats per minute
            $table->integer('respiratory_rate')->nullable(); // breaths per minute
            
            // Assessment and plan
            $table->text('assessment')->nullable(); // Doctor's assessment
            $table->text('plan')->nullable(); // Treatment plan
            $table->text('notes')->nullable(); // Additional notes
            
            // Follow-up
            $table->text('follow_up_instructions')->nullable();
            $table->date('next_visit_date')->nullable();
            
            // Status
            $table->enum('status', ['draft', 'completed', 'reviewed'])->default('draft');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['pet_id', 'visit_date']);
            $table->index(['doctor_id', 'visit_date']);
            $table->index('appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
