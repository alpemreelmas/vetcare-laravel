<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'pet_id',
        'diagnosis_code',
        'diagnosis_name',
        'description',
        'type',
        'severity',
        'status',
        'diagnosed_date',
        'resolved_date',
        'notes',
        'is_chronic',
        'requires_monitoring',
    ];

    protected function casts(): array
    {
        return [
            'diagnosed_date' => 'date',
            'resolved_date' => 'date',
            'is_chronic' => 'boolean',
            'requires_monitoring' => 'boolean',
        ];
    }

    /**
     * Get the medical record this diagnosis belongs to.
     */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /**
     * Get the pet this diagnosis belongs to.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get all treatments for this diagnosis.
     */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * Scope to filter by pet.
     */
    public function scopeForPet($query, int $petId)
    {
        return $query->where('pet_id', $petId);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get active diagnoses.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get chronic conditions.
     */
    public function scopeChronic($query)
    {
        return $query->where('is_chronic', true);
    }

    /**
     * Scope to get diagnoses requiring monitoring.
     */
    public function scopeRequiringMonitoring($query)
    {
        return $query->where('requires_monitoring', true);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('diagnosed_date', [$startDate, $endDate]);
    }

    /**
     * Check if this diagnosis is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if this diagnosis is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    /**
     * Mark this diagnosis as resolved.
     */
    public function markAsResolved(): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_date' => now()->toDateString(),
        ]);
    }

    /**
     * Get the duration of this diagnosis in days.
     */
    public function getDurationInDays(): ?int
    {
        if (!$this->diagnosed_date) {
            return null;
        }

        $endDate = $this->resolved_date ?? now();
        return $this->diagnosed_date->diffInDays($endDate);
    }
}
