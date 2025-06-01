<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'user_id',
        'pet_id',
        'start_datetime',
        'end_datetime',
        'appointment_type',
        'duration',
        'notes',
        'status',
    ];
    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'duration' => 'integer',
    ];

    /**
     * Get the doctor that owns the appointment.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the user that owns the appointment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pet that the appointment is for.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the medical record for this appointment.
     */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /**
     * Scope a query to only include appointments for a specific status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>', now());
    }

    /**
     * Scope a query to only include past appointments.
     */
    public function scopePast($query)
    {
        return $query->where('start_datetime', '<', now());
    }

    /**
     * Scope a query to only include appointments for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('start_datetime', today());
    }

    /**
     * Check if this appointment has a medical record.
     */
    public function hasMedicalRecord(): bool
    {
        return $this->medicalRecord()->exists();
    }

    /**
     * Create a medical record for this appointment.
     */
    public function createMedicalRecord(array $data = []): MedicalRecord
    {
        return $this->medicalRecord()->create(array_merge([
            'pet_id' => $this->pet_id,
            'doctor_id' => $this->doctor_id,
            'visit_date' => $this->start_datetime->toDateString(),
        ], $data));
    }
}
