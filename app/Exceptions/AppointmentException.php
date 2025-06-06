<?php

namespace App\Exceptions;

use Exception;

class AppointmentException extends Exception
{
    public static function slotNotAvailable(): self
    {
        return new self('The selected time slot is not available');
    }

    public static function petNotOwned(): self
    {
        return new self('Pet not found or you do not own this pet');
    }

    public static function appointmentNotOwned(): self
    {
        return new self('You do not own this appointment');
    }

    public static function cannotCancelPastAppointment(): self
    {
        return new self('Cannot cancel past appointments');
    }

    public static function appointmentAlreadyCompleted(): self
    {
        return new self('Appointment is already completed');
    }

    public static function appointmentAlreadyCancelled(): self
    {
        return new self('Appointment is already cancelled');
    }

    public static function doctorNotWorking(): self
    {
        return new self('Doctor is not working at the selected time');
    }

    public static function invalidTimeSlot(): self
    {
        return new self('Invalid time slot selected');
    }
} 