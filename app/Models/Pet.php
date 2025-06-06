<?php

namespace App\Models;

use App\Enums\GenderEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pet extends Model
{
    /** @use HasFactory<\Database\Factories\PetFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'species',
        'breed',
        'date_of_birth',
        'weight',
        'gender',
        'owner_id',
    ];

    protected function casts(): array
    {
        return [
            'gender' => GenderEnum::class,
            'date_of_birth' => 'date',
            'weight' => 'decimal:2',
        ];
    }

    /**
     * Get the owner of the pet.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get all appointments for this pet.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get all medical records for this pet.
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Get all diagnoses for this pet.
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    /**
     * Get all treatments for this pet.
     */
    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class);
    }

    /**
     * Get all medical documents for this pet.
     */
    public function medicalDocuments(): HasMany
    {
        return $this->hasMany(MedicalDocument::class);
    }

    /**
     * Get the pet's age in years.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->diffInYears(now());
    }

    /**
     * Scope to filter pets by species.
     */
    public function scopeBySpecies($query, string $species)
    {
        return $query->where('species', $species);
    }

    /**
     * Scope to filter pets by owner.
     */
    public function scopeByOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Get active diagnoses for this pet.
     */
    public function getActiveDiagnoses()
    {
        return $this->diagnoses()->active()->get();
    }

    /**
     * Get chronic conditions for this pet.
     */
    public function getChronicConditions()
    {
        return $this->diagnoses()->chronic()->get();
    }

    /**
     * Get current treatments for this pet.
     */
    public function getCurrentTreatments()
    {
        return $this->treatments()->current()->get();
    }

    /**
     * Get the latest medical record for this pet.
     */
    public function getLatestMedicalRecord()
    {
        return $this->medicalRecords()->latest('visit_date')->first();
    }

    /**
     * Get medical history summary for this pet.
     */
    public function getMedicalHistorySummary()
    {
        return [
            'total_visits' => $this->medicalRecords()->count(),
            'active_diagnoses' => $this->diagnoses()->active()->count(),
            'chronic_conditions' => $this->diagnoses()->chronic()->count(),
            'current_treatments' => $this->treatments()->current()->count(),
            'total_documents' => $this->medicalDocuments()->active()->count(),
            'last_visit' => $this->getLatestMedicalRecord()?->visit_date,
        ];
    }
}
