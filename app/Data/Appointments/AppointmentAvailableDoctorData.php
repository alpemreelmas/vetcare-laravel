<?php

namespace App\Data\Appointments;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class AppointmentAvailableDoctorData extends Data
{
    public function __construct(
        /**
         * @param string $date
         */
        #[Rule(['date_format:Y-m-d', 'required', 'date', 'after_or_equal:today'])]
        public string $date,

        /**
         * @param string $time
         */
        #[Rule(['required', 'string', 'regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'])]
        public string $time,
    )
    {
    }
}