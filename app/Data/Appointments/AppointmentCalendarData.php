<?php

namespace App\Data\Appointments;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class AppointmentCalendarData extends Data
{
    public function __construct(
        /**
         * @param string $start_date
         */
        #[Rule(['date_format:Y-m-d', 'required', 'date', 'after_or_equal:today'])]
        public string $start_date,

        /**
         * @param string $end_date
         */
        #[Rule(['date_format:Y-m-d', 'required', 'date', 'after_or_equal:start_date'])]
        public string $end_date,
    )
    {
    }
}