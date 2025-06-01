<?php

namespace App\Data\Appointments;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class AppointmentListData extends Data
{
    public function __construct(
        #[Rule(['sometimes', 'integer', 'exists:doctors,id'])]
        public ?int $doctor_id = null,

        #[Rule(['sometimes', 'integer', 'exists:pets,id'])]
        public ?int $pet_id = null,

        #[Rule(['sometimes', 'date_format:Y-m-d'])]
        public ?string $start_date = null,

        #[Rule(['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'])]
        public ?string $end_date = null,

        #[Rule(['sometimes', 'string', 'in:pending,confirmed,completed,cancelled,no-show'])]
        public ?string $status = null,

        #[Rule(['sometimes', 'string', 'in:regular,emergency,surgery,vaccination,checkup,consultation'])]
        public ?string $appointment_type = null,

        #[Rule(['sometimes', 'integer', 'min:1', 'max:100'])]
        public int $per_page = 15,

        #[Rule(['sometimes', 'integer', 'min:1'])]
        public int $page = 1,
    ) {
    }
} 