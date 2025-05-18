<?php

namespace App\Services;

use App\Data\Appointments\AvailableSlotsData;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentCalendarService
{
    public const SLOT_DURATION = 20;
    private const DEFAULT_WORKING_HOURS_START = 9;
    private const DEFAULT_WORKING_HOURS_END = 19;

    public function __construct(
        private readonly DoctorAvailabilityService $doctorAvailabilityService
    )
    {
    }

    public function getCalendarData(Carbon $startDate, Carbon $endDate): array
    {
        $doctors = $this->getActiveDoctors();

        if ($doctors->isEmpty()) {
            return [];
        }

        $calendarData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $requestData = new AvailableSlotsData(date: $currentDate->format('Y-m-d'));
            $multiDoctorSlots = $this->doctorAvailabilityService->getMultiDoctorSlots($requestData, $doctors);

            $slotsByTime = [];

            foreach ($multiDoctorSlots as $slots) {
                foreach ($slots as $slot) {
                    $start = $slot['start'];
                    $key = $start;
                    $slotsByTime[$key] = ($slotsByTime[$key] ?? 0) + 1;
                }
            }

            $formattedSlots = collect($slotsByTime)->map(function ($count, $start) use ($currentDate, $doctors) {
                $startTime = $currentDate->copy()->setTimeFromTimeString($start);
                $endTime = $startTime->copy()->addMinutes(self::SLOT_DURATION);
                return [
                    'time' => $start,
                    'time_range' => $start . ' - ' . $endTime->format('H:i'),
                    'available_count' => $count,
                    'total_doctors' => $doctors->count()
                ];
            })->values()->all();

            $calendarData[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $currentDate->format('l'),
                'available_slots' => $formattedSlots,
                'total_available_slots' => collect($formattedSlots)->sum('available_count')
            ];

            $currentDate->addDay();
        }

        return $calendarData;
    }

    public function getAvailableDoctorsForSlot(Carbon $date, string $time): array
    {
        $doctors = $this->getActiveDoctors();
        $slotStart = $date->copy()->setTimeFromTimeString($time);
        $slotEnd = $slotStart->copy()->addMinutes(self::SLOT_DURATION);

        $requestData = new AvailableSlotsData(date: $date->format('Y-m-d'));
        $multiDoctorSlots = $this->doctorAvailabilityService->getMultiDoctorSlots($requestData, $doctors);

        $availableDoctors = [];
        $timeKey = $slotStart->format('H:i');

        foreach ($doctors as $doctor) {
            foreach ($multiDoctorSlots[$doctor->id] ?? [] as $slot) {
                if ($slot['start'] === $timeKey) {
                    $availableDoctors[] = $this->formatDoctorForSlot($doctor, $slotStart, $slotEnd, $date);
                    break;
                }
            }
        }

        return $availableDoctors;
    }

    private function formatDoctorForSlot(Doctor $doctor, Carbon $slotStart, Carbon $slotEnd, Carbon $date): array
    {
        return [
            'id' => $doctor->id,
            'name' => $doctor->name,
            'specialization' => $doctor->specialization,
            'working_hours' => $doctor->working_hours,
            'slot' => [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'date' => $date->format('Y-m-d')
            ]
        ];
    }

    private function getActiveDoctors(): Collection
    {
        return Doctor::whereNotNull('working_hours')->get();
    }
}
