<?php

namespace App\Data\Appointments;

use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;

class AvailableSlotsData extends Data
{
    public function __construct(
        /**
         * @param string $date
         */
        #[Rule(['date_format:Y-m-d', 'required'])]
        public string $date,
    )
    {
    }
}