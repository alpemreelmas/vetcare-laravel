<?php

namespace App\Services;

use App\Data\Appointments\AvailableSlotsData;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\RestrictedZone;
use Carbon\Carbon;

class DoctorAvailabilityService
{
    /**
     * Duration of each appointment slot in minutes
     * 15 minutes for regular check and 5 minutes for rest, sum of it 20 minutes
     */
    private const SLOT_DURATION = 20;

    /**
     * Get available appointment slots for a doctor on a specified date
     */
    public function getSlots(AvailableSlotsData $request, Doctor $doctor)
    {
        $selectedDate = Carbon::parse($request->date)->startOfDay();
        $now = Carbon::now();

        // Check if working hours are properly set
        $workingHoursString = $doctor->working_hours;
        if (!$this->hasValidWorkingHours($workingHoursString)) {
            return null;
        }

        // Parse working hours
        [$startTime, $endTime] = $this->getWorkingHoursPeriod($selectedDate, $workingHoursString);

        // If today, adjust start time to not show past slots
        if ($selectedDate->isSameDay($now) && $now->gt($startTime)) {
            // Round up to the next slot time
            $startTime = $now->copy();
            $minutes = $startTime->minute;
            $roundedMinutes = ceil($minutes / self::SLOT_DURATION) * self::SLOT_DURATION;

            // If we need to go to the next hour
            if ($roundedMinutes >= 60) {
                $startTime->addHour();
                $roundedMinutes = 0;
            }

            $startTime->setMinute($roundedMinutes)->setSecond(0);
        }

        // No need to continue if start time is already past end time
        if ($startTime->gte($endTime)) {
            return null;
        }

        // Get booked appointments
        $appointments = $this->getBookedAppointments($doctor->id, $startTime, $endTime);

        // Get restricted zones
        $restrictedZones = $this->getRestrictedZones($doctor->id, $startTime, $endTime);

        // Calculate available slots
        $availableSlots = $this->calculateAvailableSlots($startTime, $endTime, $appointments, $restrictedZones);

        return $availableSlots;
    }

    /**
     * Check if working hours are valid
     */
    private function hasValidWorkingHours(?string $workingHours): bool
    {
        return $workingHours && str_contains($workingHours, '-');
    }

    /**
     * Get working hours period (start and end time)
     */
    private function getWorkingHoursPeriod(Carbon $date, string $workingHoursString): array
    {
        [$start, $end] = explode('-', $workingHoursString);
        $startTime = $date->copy()->setTimeFromTimeString($start);
        $endTime = $date->copy()->setTimeFromTimeString($end);

        return [$startTime, $endTime];
    }

    /**
     * Get booked appointments for the specified time period
     */
    private function getBookedAppointments(int $doctorId, Carbon $startTime, Carbon $endTime)
    {
        // Convert Carbon timestamps to Unix timestamps (milliseconds)
        $startTimestamp = $startTime->timestamp * 1000;
        $endTimestamp = $endTime->timestamp * 1000;

        return Appointment::where('doctor_id', $doctorId)
            ->where(function ($query) use ($startTimestamp, $endTimestamp) {
                $query->whereBetween('start_datetime', [$startTimestamp, $endTimestamp])
                    ->orWhereBetween('end_datetime', [$startTimestamp, $endTimestamp])
                    ->orWhere(function ($q) use ($startTimestamp, $endTimestamp) {
                        $q->where('start_datetime', '<', $startTimestamp)
                            ->where('end_datetime', '>', $startTimestamp);
                    });
            })->get();
    }

    /**
     * Get restricted zones for the specified time period
     */
    private function getRestrictedZones(int $doctorId, Carbon $startTime, Carbon $endTime)
    {
        // Convert Carbon timestamps to Unix timestamps (milliseconds)
        $startTimestamp = $startTime->timestamp * 1000;
        $endTimestamp = $endTime->timestamp * 1000;

        return RestrictedZone::where('doctor_id', $doctorId)
            ->where(function ($query) use ($startTimestamp, $endTimestamp) {
                $query->whereBetween('start_datetime', [$startTimestamp, $endTimestamp])
                    ->orWhereBetween('end_datetime', [$startTimestamp, $endTimestamp])
                    ->orWhere(function ($q) use ($startTimestamp, $endTimestamp) {
                        $q->where('start_datetime', '<', $startTimestamp)
                            ->where('end_datetime', '>', $startTimestamp);
                    });
            })->get();
    }

    /**
     * Calculate available slots based on working hours, appointments and restricted zones
     */
    private function calculateAvailableSlots(Carbon $startTime, Carbon $endTime, $appointments, $restrictedZones): array
    {
        $availableSlots = [];
        $cursor = $startTime->copy();

        while ($cursor->lt($endTime)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes(self::SLOT_DURATION);

            // Break if slot exceeds end time
            if ($slotEnd->gt($endTime)) break;

            // Check if slot conflicts with any appointment or restricted zone
            if (!$this->hasConflict($slotStart, $slotEnd, $appointments, $restrictedZones)) {
                $availableSlots[] = [
                    'start' => $slotStart->format('H:i'),
                    'end' => $slotEnd->format('H:i'),
                ];
            }

            $cursor->addMinutes(self::SLOT_DURATION);
        }

        return $availableSlots;
    }

    /**
     * Check if a slot conflicts with any appointment or restricted zone
     */
    private function hasConflict(Carbon $slotStart, Carbon $slotEnd, $appointments, $restrictedZones): bool
    {
        // Convert slot times to timestamps for comparison
        $slotStartTimestamp = $slotStart->timestamp * 1000;
        $slotEndTimestamp = $slotEnd->timestamp * 1000;

        // Check appointments
        $appointmentConflict = $appointments->first(function ($appointment) use ($slotStartTimestamp, $slotEndTimestamp) {
            return $slotStartTimestamp < $appointment->end_datetime->timestamp && $slotEndTimestamp > $appointment->start_datetime->timestamp;
        });

        if ($appointmentConflict) {
            return true;
        }

        // Check restricted zones
        $restrictedZoneConflict = $restrictedZones->first(function ($restrictedZone) use ($slotStartTimestamp, $slotEndTimestamp) {
            return $slotStartTimestamp < $restrictedZone->end_datetime && $slotEndTimestamp > $restrictedZone->start_datetime;
        });

        return $restrictedZoneConflict !== null;
    }
}