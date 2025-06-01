<?php

namespace App\Data\Appointments;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateAppointmentData extends Data
{
    public function __construct(
        #[Rule(['sometimes', 'integer', 'exists:doctors,id'])]
        public ?int $doctor_id = null,

        #[Rule(['sometimes', 'integer', 'exists:pets,id'])]
        public ?int $pet_id = null,

        #[Rule(['sometimes', 'date_format:Y-m-d', 'after_or_equal:today'])]
        public ?string $date = null,

        #[Rule(['sometimes', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'])]
        public ?string $time = null,

        #[Rule(['sometimes', 'string', 'in:regular,emergency,surgery,vaccination,checkup,consultation'])]
        public ?string $appointment_type = null,

        #[Rule(['sometimes', 'integer', 'min:15', 'max:120'])]
        public ?int $duration = null,

        #[Rule(['sometimes', 'string', 'in:pending,confirmed,completed,cancelled,no-show'])]
        public ?string $status = null,

        #[Rule(['nullable', 'string', 'max:1000'])]
        public ?string $notes = null,
    ) {
    }
} 