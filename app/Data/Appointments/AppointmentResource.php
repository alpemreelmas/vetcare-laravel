<?php

namespace App\Data\Appointments;

use App\Models\Appointment;
use Spatie\LaravelData\Data;

class AppointmentResource extends Data
{
    public function __construct(
        public int $id,
        public int $doctor_id,
        public string $doctor_name,
        public string $doctor_specialization,
        public int $user_id,
        public string $user_name,
        public string $user_email,
        public int $pet_id,
        public string $pet_name,
        public string $pet_species,
        public string $pet_breed,
        public string $start_datetime,
        public string $end_datetime,
        public string $appointment_type,
        public int $duration,
        public ?string $notes,
        public string $status,
        public string $created_at,
        public string $updated_at,
    ) {
    }

    public static function fromModel(Appointment $appointment): self
    {
        return new self(
            id: $appointment->id,
            doctor_id: $appointment->doctor_id,
            doctor_name: $appointment->doctor->user->name,
            doctor_specialization: $appointment->doctor->specialization ?? 'General Veterinarian',
            user_id: $appointment->user_id,
            user_name: $appointment->user->name,
            user_email: $appointment->user->email,
            pet_id: $appointment->pet_id,
            pet_name: $appointment->pet->name,
            pet_species: $appointment->pet->species,
            pet_breed: $appointment->pet->breed,
            start_datetime: $appointment->start_datetime->format('Y-m-d H:i:s'),
            end_datetime: $appointment->end_datetime->format('Y-m-d H:i:s'),
            appointment_type: $appointment->appointment_type,
            duration: $appointment->duration,
            notes: $appointment->notes,
            status: $appointment->status,
            created_at: $appointment->created_at->format('Y-m-d H:i:s'),
            updated_at: $appointment->updated_at->format('Y-m-d H:i:s'),
        );
    }
} 