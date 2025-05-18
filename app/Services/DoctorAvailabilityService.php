<?php

namespace App\Services;

use App\Data\Appointments\AvailableSlotsData;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\RestrictedZone;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DoctorAvailabilityService
{
    private const SLOT_DURATION = 20;

    public function getSlots(AvailableSlotsData $request, Doctor $doctor): ?array
    {
        return $this->getAvailableSlotsForDoctorOnDate($request->date, $doctor);
    }

    public function getAvailableSlotsForDoctorOnDate(string $date, Doctor $doctor): ?array
    {
        $selectedDate = Carbon::parse($date)->startOfDay();
        $now = Carbon::now();

        $workingHoursString = $doctor->working_hours;
        if (!$this->hasValidWorkingHours($workingHoursString)) {
            return null;
        }

        [$startTime, $endTime] = $this->getWorkingHoursPeriod($selectedDate, $workingHoursString);

        if ($selectedDate->isSameDay($now) && $now->gt($startTime)) {
            $startTime = $this->roundUpStartTime($now);
        }

        if ($startTime->gte($endTime)) {
            return null;
        }

        $appointments = $this->getBookedAppointments($doctor->id, $startTime, $endTime);
        $restrictedZones = $this->getRestrictedZones($doctor->id, $startTime, $endTime);

        return $this->calculateAvailableSlots($startTime, $endTime, $appointments, $restrictedZones);
    }

    public function getMultiDoctorSlots(AvailableSlotsData $request, Collection $doctors): array
    {
        $selectedDate = Carbon::parse($request->date)->startOfDay();
        $now = Carbon::now();

        $doctorWorkingHours = $doctors->filter(fn($doc) => $this->hasValidWorkingHours($doc->working_hours))
            ->mapWithKeys(fn($doc) => [$doc->id => $this->getWorkingHoursPeriod($selectedDate, $doc->working_hours)]);

        $minStart = $doctorWorkingHours->map(fn($times) => $times[0])->min();
        $maxEnd = $doctorWorkingHours->map(fn($times) => $times[1])->max();

        $appointments = Appointment::whereIn('doctor_id', $doctors->pluck('id'))
            ->whereBetween('start_datetime', [$minStart->timestamp * 1000, $maxEnd->timestamp * 1000])
            ->orWhereBetween('end_datetime', [$minStart->timestamp * 1000, $maxEnd->timestamp * 1000])
            ->get()
            ->groupBy('doctor_id');

        $restrictedZones = RestrictedZone::whereIn('doctor_id', $doctors->pluck('id'))
            ->whereBetween('start_datetime', [$minStart->timestamp * 1000, $maxEnd->timestamp * 1000])
            ->orWhereBetween('end_datetime', [$minStart->timestamp * 1000, $maxEnd->timestamp * 1000])
            ->get()
            ->groupBy('doctor_id');

        $results = [];

        foreach ($doctors as $doctor) {
            if (!$doctorWorkingHours->has($doctor->id)) continue;

            [$startTime, $endTime] = $doctorWorkingHours[$doctor->id];

            if ($selectedDate->isSameDay($now) && $now->gt($startTime)) {
                $startTime = $this->roundUpStartTime($now);
            }

            if ($startTime->gte($endTime)) {
                $results[$doctor->id] = [];
                continue;
            }

            $doctorAppointments = $appointments[$doctor->id] ?? collect();
            $doctorRestricted = $restrictedZones[$doctor->id] ?? collect();

            $results[$doctor->id] = $this->calculateAvailableSlots($startTime, $endTime, $doctorAppointments, $doctorRestricted);
        }

        return $results;
    }

    public function getDoctorsAvailableInTimeRange(Carbon $startTime, Carbon $endTime, Collection $doctors): Collection
    {
        return $doctors->filter(function (Doctor $doctor) use ($startTime, $endTime) {
            $workingHours = $doctor->working_hours;
            if (!$this->hasValidWorkingHours($workingHours)) return false;

            [$start, $end] = $this->getWorkingHoursPeriod($startTime->copy(), $workingHours);
            if ($startTime->lt($end) && $endTime->gt($start)) {
                $appointments = $this->getBookedAppointments($doctor->id, $startTime, $endTime);
                $restrictedZones = $this->getRestrictedZones($doctor->id, $startTime, $endTime);

                return !$this->hasConflict($startTime, $endTime, $appointments, $restrictedZones);
            }

            return false;
        });
    }

    private function roundUpStartTime(Carbon $time): Carbon
    {
        $minutes = $time->minute;
        $roundedMinutes = ceil($minutes / self::SLOT_DURATION) * self::SLOT_DURATION;

        if ($roundedMinutes >= 60) {
            $time->addHour();
            $roundedMinutes = 0;
        }

        return $time->copy()->setMinute($roundedMinutes)->setSecond(0);
    }

    private function hasValidWorkingHours(?string $workingHours): bool
    {
        return $workingHours && str_contains($workingHours, '-');
    }

    private function getWorkingHoursPeriod(Carbon $date, string $workingHoursString): array
    {
        [$start, $end] = explode('-', $workingHoursString);
        return [
            $date->copy()->setTimeFromTimeString($start),
            $date->copy()->setTimeFromTimeString($end),
        ];
    }

    private function getBookedAppointments(int $doctorId, Carbon $startTime, Carbon $endTime)
    {
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

    private function getRestrictedZones(int $doctorId, Carbon $startTime, Carbon $endTime)
    {
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

    private function calculateAvailableSlots(Carbon $startTime, Carbon $endTime, $appointments, $restrictedZones): array
    {
        $availableSlots = [];
        $cursor = $startTime->copy();

        while ($cursor->lt($endTime)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes(self::SLOT_DURATION);

            if ($slotEnd->gt($endTime)) break;

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

    private function hasConflict(Carbon $slotStart, Carbon $slotEnd, $appointments, $restrictedZones): bool
    {
        $slotStartTimestamp = $slotStart->timestamp * 1000;
        $slotEndTimestamp = $slotEnd->timestamp * 1000;

        $appointmentConflict = $appointments->first(fn($a) => $slotStartTimestamp < $a->end_datetime->timestamp && $slotEndTimestamp > $a->start_datetime->timestamp);
        if ($appointmentConflict) return true;

        $restrictedZoneConflict = $restrictedZones->first(fn($z) => $slotStartTimestamp < $z->end_datetime && $slotEndTimestamp > $z->start_datetime);

        return $restrictedZoneConflict !== null;
    }
}
