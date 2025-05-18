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

    /**
     * Get calendar data for the specified date range
     */
    public function getCalendarData(Carbon $startDate, Carbon $endDate): array
    {
        $doctors = $this->getActiveDoctors();

        if ($doctors->isEmpty()) {
            return [];
        }

        $calendarData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayData = $this->buildDayData($doctors, $currentDate);

            // Only include dates that have available slots
            if ($dayData['total_available_slots'] > 0) {
                $calendarData[] = $dayData;
            }

            $currentDate->addDay();
        }

        return $calendarData;
    }

    /**
     * Get available doctors for a specific time slot
     */
    public function getAvailableDoctorsForSlot(Carbon $date, string $time): array
    {
        $doctors = $this->getActiveDoctors();
        $slotStart = $date->copy()->setTimeFromTimeString($time);
        $slotEnd = $slotStart->copy()->addMinutes(self::SLOT_DURATION);

        $availableDoctors = [];

        foreach ($doctors as $doctor) {
            if ($this->isDoctorAvailableAtTime($doctor, $slotStart)) {
                $availableDoctors[] = $this->formatDoctorForSlot($doctor, $slotStart, $slotEnd, $date);
            }
        }

        return $availableDoctors;
    }

    /**
     * Build day data for a specific date
     */
    private function buildDayData(Collection $doctors, Carbon $date): array
    {
        $availableSlots = $this->getAvailableSlotsForDate($doctors, $date);

        return [
            'date' => $date->format('Y-m-d'),
            'day_name' => $date->format('l'),
            'available_slots' => $availableSlots,
            'total_available_slots' => collect($availableSlots)->sum('available_count')
        ];
    }

    /**
     * Get available slots for all doctors on a specific date
     */
    private function getAvailableSlotsForDate(Collection $doctors, Carbon $date): array
    {
        $timeSlots = [];
        $allPossibleSlots = $this->generateAllPossibleSlots($date);

        foreach ($allPossibleSlots as $slot) {
            $slotStart = $date->copy()->setTimeFromTimeString($slot['start']);
            $availableCount = $this->countAvailableDoctorsForSlot($doctors, $slotStart);

            if ($availableCount > 0) {
                $timeSlots[] = [
                    'time' => $slot['start'],
                    'time_range' => $slot['start'] . ' - ' . $slot['end'],
                    'available_count' => $availableCount,
                    'total_doctors' => $doctors->count()
                ];
            }
        }

        return $timeSlots;
    }

    /**
     * Count available doctors for a specific slot
     */
    private function countAvailableDoctorsForSlot(Collection $doctors, Carbon $slotStart): int
    {
        $availableCount = 0;

        foreach ($doctors as $doctor) {
            if ($this->isDoctorAvailableAtTime($doctor, $slotStart)) {
                $availableCount++;
            }
        }

        return $availableCount;
    }

    /**
     * Generate all possible time slots for a day
     */
    private function generateAllPossibleSlots(Carbon $date): array
    {
        $slots = [];
        $start = $date->copy()->setTime(self::DEFAULT_WORKING_HOURS_START, 0);
        $end = $date->copy()->setTime(self::DEFAULT_WORKING_HOURS_END, 0);

        $cursor = $start->copy();

        while ($cursor->addMinutes(self::SLOT_DURATION)->lte($end)) {
            $slotStart = $cursor->copy()->subMinutes(self::SLOT_DURATION);
            $slotEnd = $cursor->copy();

            $slots[] = [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i')
            ];
        }

        return $slots;
    }

    /**
     * Check if a doctor is available at a specific time
     */
    private function isDoctorAvailableAtTime(Doctor $doctor, Carbon $slotStart): bool
    {
        $requestData = new AvailableSlotsData(date: $slotStart->format('Y-m-d'));
        $availableSlots = $this->doctorAvailabilityService->getSlots($requestData, $doctor);

        if (empty($availableSlots)) {
            return false;
        }

        $requestedSlotTime = $slotStart->format('H:i');

        foreach ($availableSlots as $slot) {
            if ($slot['start'] === $requestedSlotTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format doctor data for slot response
     */
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

    /**
     * Get all active doctors
     */
    private function getActiveDoctors(): Collection
    {
        return Doctor::whereNotNull('working_hours')->get();
    }
}