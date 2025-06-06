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
        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            
            // Diagnosis information
            $table->string('diagnosis_code')->nullable(); // ICD-10 or veterinary equivalent
            $table->string('diagnosis_name');
            $table->text('description')->nullable();
            
            // Classification
            $table->enum('type', ['primary', 'secondary', 'differential', 'rule_out'])->default('primary');
            $table->enum('severity', ['mild', 'moderate', 'severe', 'critical'])->nullable();
            $table->enum('status', ['active', 'resolved', 'chronic', 'monitoring'])->default('active');
            
            // Timeline
            $table->date('diagnosed_date');
            $table->date('resolved_date')->nullable();
            
            // Additional information
            $table->text('notes')->nullable();
            $table->boolean('is_chronic')->default(false);
            $table->boolean('requires_monitoring')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['pet_id', 'diagnosed_date']);
            $table->index(['medical_record_id']);
            $table->index(['diagnosis_code']);
            $table->index(['status', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnoses');
    }
};
