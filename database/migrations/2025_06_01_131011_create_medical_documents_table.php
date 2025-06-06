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
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('pet_id')->constrained()->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            
            // Document information
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', [
                'xray', 
                'lab_report', 
                'blood_work', 
                'ultrasound', 
                'ct_scan', 
                'mri', 
                'prescription', 
                'vaccination_record', 
                'surgical_report', 
                'pathology_report',
                'photo',
                'other'
            ]);
            
            // File information
            $table->string('file_name'); // Original filename
            $table->string('file_path'); // Storage path
            $table->string('file_type'); // MIME type
            $table->bigInteger('file_size'); // Size in bytes
            $table->string('file_hash')->nullable(); // For duplicate detection
            
            // Metadata
            $table->date('document_date')->nullable(); // Date the document was created/taken
            $table->text('tags')->nullable(); // JSON array of tags for searching
            $table->boolean('is_sensitive')->default(false); // For privacy control
            $table->boolean('is_archived')->default(false);
            
            // Access control
            $table->enum('visibility', ['private', 'doctor_only', 'owner_and_doctor', 'public'])->default('doctor_only');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['pet_id', 'type']);
            $table->index(['medical_record_id']);
            $table->index(['document_date']);
            $table->index(['type', 'is_archived']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_documents');
    }
};
