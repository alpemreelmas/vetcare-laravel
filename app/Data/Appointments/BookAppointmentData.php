<?php

namespace App\Data\Appointments;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class BookAppointmentData extends Data
{
    public function __construct(
        #[Rule(['required', 'integer', 'exists:doctors,id'])]
        public int $doctor_id,

        #[Rule(['required', 'integer', 'exists:pets,id'])]
        public int $pet_id,

        #[Rule(['required', 'date_format:Y-m-d', 'after_or_equal:today'])]
        public string $date,

        #[Rule(['required', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'])]
        public string $time,

        #[Rule(['required', 'string', 'in:regular,emergency,surgery,vaccination,checkup,consultation'])]
        public string $appointment_type,

        #[Rule(['required', 'integer', 'min:15', 'max:120'])]
        public int $duration,

        #[Rule(['nullable', 'string', 'max:1000'])]
        public ?string $notes = null,
    ) {
    }
} 