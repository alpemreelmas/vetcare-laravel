<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Data\Appointments\AvailableSlotsData;
use App\Models\Doctor;
use App\Services\DoctorAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    private const SLOT_DURATION = 20;

    /**
     * Get calendar view with availability for all doctors
     * Returns available time slots grouped by date
     */
    public function calendar(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date', now()))->startOfDay();
        $endDate = Carbon::parse($request->get('end_date', now()->addDays(30)))->endOfDay();

        // Get all active doctors
        $doctors = Doctor::all();

        if ($doctors->isEmpty()) {
            return ResponseHelper::success('No active doctors found', []);
        }

        $calendarData = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayData = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $currentDate->format('l'),
                'available_slots' => $this->getAvailableSlotsForDate($doctors, $currentDate),
                'total_available_slots' => 0
            ];

            // Count total available slots for this date
            $dayData['total_available_slots'] = collect($dayData['available_slots'])->sum('available_count');

            // Only include dates that have available slots
            if ($dayData['total_available_slots'] > 0) {
                $calendarData[] = $dayData;
            }

            $currentDate->addDay();
        }

        return ResponseHelper::success('Calendar availability retrieved successfully', [
            'calendar' => $calendarData,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Get available doctors for a specific date and time
     */
    public function getAvailableDoctors(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'time' => 'required|string', // Format: HH:MM
        ]);

        $selectedDate = Carbon::parse($request->date)->startOfDay();
        $selectedTime = $request->time;

        // Parse the time and create start and end datetime for the slot
        $slotStart = $selectedDate->copy()->setTimeFromTimeString($selectedTime);
        $slotEnd = $slotStart->copy()->addMinutes(self::SLOT_DURATION);

        // Get all active doctors
        $doctors = Doctor::all();

        $availableDoctors = [];

        foreach ($doctors as $doctor) {
            if ($this->isDoctorAvailableAtTime($doctor, $slotStart, $slotEnd)) {
                $availableDoctors[] = [
                    'id' => $doctor->id,
                    'name' => $doctor->user->name,
                    'specialization' => $doctor->specialization,
                    'working_hours' => $doctor->working_hours,
                    'slot' => [
                        'start' => $slotStart->format('H:i'),
                        'end' => $slotEnd->format('H:i'),
                        'date' => $selectedDate->format('Y-m-d')
                    ]
                ];
            }
        }

        return ResponseHelper::success('Available doctors retrieved successfully', [
            'doctors' => $availableDoctors,
            'requested_slot' => [
                'date' => $selectedDate->format('Y-m-d'),
                'time' => $selectedTime,
                'duration' => self::SLOT_DURATION . ' minutes'
            ],
            'total_available' => count($availableDoctors)
        ]);
    }

    /**
     * Get available slots for all doctors on a specific date
     */
    private function getAvailableSlotsForDate($doctors, Carbon $date): array
    {
        $timeSlots = [];
        $allPossibleSlots = $this->generateAllPossibleSlots($date);
        foreach ($allPossibleSlots as $slot) {
            $slotStart = $date->copy()->setTimeFromTimeString($slot['start']);
            $slotEnd = $date->copy()->setTimeFromTimeString($slot['end']);

            $availableCount = 0;

            foreach ($doctors as $doctor) {
                if ($this->isDoctorAvailableAtTime($doctor, $slotStart, $slotEnd)) {
                    $availableCount++;
                }
            }

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
     * Generate all possible time slots for a day (based on common working hours)
     */
    private function generateAllPossibleSlots(Carbon $date): array
    {
        $slots = [];
        $start = $date->copy()->setTime(9, 0); // Start at 8:00 AM
        $end = $date->copy()->setTime(19, 0);  // End at 6:00 PM

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
     * Check if a doctor is available at a specific time using the service
     */
    private function isDoctorAvailableAtTime(Doctor $doctor, Carbon $slotStart, Carbon $slotEnd): bool
    {
        // Create request data for the service
        $requestData = new AvailableSlotsData(date: $slotStart->format('Y-m-d'));

        // Get all available slots for the doctor on this date
        $response = app(DoctorAvailabilityService::class)->getSlots($requestData, $doctor);
        // Check if the response contains slots
        if (!isset($response) || empty($response)) {
            return false;
        }

        $requestedSlotTime = $slotStart->format('H:i');

        // Check if the requested time slot exists in available slots
        foreach ($response as $slot) {
            if ($slot['start'] === $requestedSlotTime) {
                return true;
            }
        }

        return false;
    }
}
