<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Data\Appointments\AppointmentAvailableDoctorData;
use App\Data\Appointments\AppointmentCalendarData;
use App\Data\Appointments\AvailableSlotsData;
use App\Models\Doctor;
use App\Services\AppointmentCalendarService;
use App\Services\DoctorAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    private DoctorAvailabilityService $doctorAvailabilityService;
    private AppointmentCalendarService $calendarService;

    public function __construct(DoctorAvailabilityService $doctorAvailabilityService, AppointmentCalendarService $calendarService)
    {
        $this->doctorAvailabilityService = $doctorAvailabilityService;
        $this->calendarService = $calendarService;
    }

    /**
     * Get calendar view with availability for all doctors
     * Returns available time slots grouped by date
     */
    public function calendar(AppointmentCalendarData $request): JsonResponse
    {
        $startDate = Carbon::createFromFormat('Y-m-d', $request->start_date);
        $endDate = Carbon::createFromFormat('Y-m-d', $request->end_date);
        $calendarData = $this->calendarService->getCalendarData(
            $startDate,
            $endDate
        );

        if (empty($calendarData)) {
            return ResponseHelper::success('No available appointments found in the selected date range', [
                'calendar' => [],
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]);
        }

        return ResponseHelper::success('Calendar availability retrieved successfully', [
            'calendar' => $calendarData,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    /**
     * Get available doctors for a specific date and time
     */
    public function getAvailableDoctors(AppointmentAvailableDoctorData $request): JsonResponse
    {
        $date = Carbon::createFromFormat('Y-m-d', $request->date);
        $availableDoctors = $this->calendarService->getAvailableDoctorsForSlot(
            $date,
            $request->time
        );

        return ResponseHelper::success('Available doctors retrieved successfully', [
            'doctors' => $availableDoctors,
            'requested_slot' => [
                'date' => $date,
                'time' => $request->time,
                'duration' => AppointmentCalendarService::SLOT_DURATION . ' minutes'
            ],
            'total_available' => count($availableDoctors)
        ]);
    }

    /**
     * Get available time slots for a specific doctor
     */
    public function getAvailableSlotsForDoctor(AvailableSlotsData $request, Doctor $doctor): JsonResponse
    {
        $availableSlots = $this->doctorAvailabilityService->getSlots($request, $doctor);

        return ResponseHelper::success('Available slots retrieved successfully', [
            'doctor' => $doctor,
            'available_slots' => $availableSlots
        ]);
    }
}