<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'pet_id',
        'doctor_id',
        'visit_date',
        'chief_complaint',
        'history_of_present_illness',
        'physical_examination',
        'weight',
        'temperature',
        'heart_rate',
        'respiratory_rate',
        'assessment',
        'plan',
        'notes',
        'follow_up_instructions',
        'next_visit_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'next_visit_date' => 'date',
            'weight' => 'decimal:2',
            'temperature' => 'decimal:2',
            'heart_rate' => 'integer',
            'respiratory_rate' => 'integer',
        ];
    }

    /**
     * Get the appointment this medical record belongs to.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the pet this medical record belongs to.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the doctor who created this medical record.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get all diagnoses for this medical record.
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    /**
     * Get all treatments for this medical record.
     */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * Get all medical documents for this medical record.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class);
    }

    /**
     * Scope to filter by pet.
     */
    public function scopeForPet($query, int $petId)
    {
        return $query->where('pet_id', $petId);
    }

    /**
     * Scope to filter by doctor.
     */
    public function scopeByDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('visit_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get the primary diagnosis for this record.
     */
    public function primaryDiagnosis()
    {
        return $this->diagnoses()->where('type', 'primary')->first();
    }

    /**
     * Check if this record is complete.
     */
    public function isComplete(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark this record as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update(['status' => 'completed']);
    }
}
