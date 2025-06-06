<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Data\Appointments\AppointmentAvailableDoctorData;
use App\Data\Appointments\AppointmentCalendarData;
use App\Data\Appointments\AppointmentListData;
use App\Data\Appointments\AppointmentResource;
use App\Data\Appointments\AvailableSlotsData;
use App\Data\Appointments\BookAppointmentData;
use App\Data\Appointments\UpdateAppointmentData;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentCalendarService;
use App\Services\DoctorAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    private DoctorAvailabilityService $doctorAvailabilityService;
    private AppointmentCalendarService $calendarService;
    private AppointmentBookingService $bookingService;

    public function __construct(
        DoctorAvailabilityService $doctorAvailabilityService,
        AppointmentCalendarService $calendarService,
        AppointmentBookingService $bookingService
    ) {
        $this->doctorAvailabilityService = $doctorAvailabilityService;
        $this->calendarService = $calendarService;
        $this->bookingService = $bookingService;
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

    /**
     * List appointments with filtering
     */
    public function index(AppointmentListData $request): JsonResponse
    {
        $user = auth()->user();
        
        $filters = [
            'doctor_id' => $request->doctor_id,
            'pet_id' => $request->pet_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status,
            'appointment_type' => $request->appointment_type,
        ];

        $appointments = $this->bookingService->getUserAppointments($user, $filters);
        
        // Convert to resources
        $appointmentResources = $appointments->map(function ($appointment) {
            return AppointmentResource::fromModel($appointment);
        });

        return ResponseHelper::success('Appointments retrieved successfully', [
            'appointments' => $appointmentResources,
            'total' => $appointments->count(),
            'filters_applied' => array_filter($filters)
        ]);
    }

    /**
     * Show a specific appointment
     */
    public function show(Appointment $appointment): JsonResponse
    {
        $user = auth()->user();
        
        // Check if user owns this appointment
        if ($appointment->user_id !== $user->id) {
            return ResponseHelper::error('Appointment not found', 404);
        }

        $appointment->load(['doctor.user', 'pet']);
        $appointmentResource = AppointmentResource::fromModel($appointment);

        return ResponseHelper::success('Appointment retrieved successfully', [
            'appointment' => $appointmentResource
        ]);
    }

    /**
     * Book a new appointment
     */
    public function store(BookAppointmentData $request): JsonResponse
    {
            $user = auth()->user();
            $result = $this->bookingService->bookAppointment($request, $user);

            $appointment = $result['appointment'];
            $appointment->load(['doctor.user', 'pet']);
            $appointmentResource = AppointmentResource::fromModel($appointment);

            return ResponseHelper::success($result['message'], [
                'appointment' => $appointmentResource
            ], 201);
    }

    /**
     * Update an existing appointment
     */
    public function update(UpdateAppointmentData $request, Appointment $appointment): JsonResponse
    {
            $user = auth()->user();
            $result = $this->bookingService->updateAppointment($appointment, $request, $user);

            $updatedAppointment = $result['appointment'];
            $updatedAppointment->load(['doctor.user', 'pet']);
            $appointmentResource = AppointmentResource::fromModel($updatedAppointment);

            return ResponseHelper::success($result['message'], [
                'appointment' => $appointmentResource
            ]);
    }

    /**
     * Cancel an appointment
     */
    public function cancel(Appointment $appointment): JsonResponse
    {
            $user = auth()->user();
            $result = $this->bookingService->cancelAppointment($appointment, $user);

            $cancelledAppointment = $result['appointment'];
            $cancelledAppointment->load(['doctor.user', 'pet']);
            $appointmentResource = AppointmentResource::fromModel($cancelledAppointment);

            return ResponseHelper::success($result['message'], [
                'appointment' => $appointmentResource
            ]);
    }

    /**
     * Delete an appointment (hard delete - admin only)
     */
    public function destroy(Appointment $appointment): JsonResponse
    {
        $user = auth()->user();
        
        // Only allow admin or appointment owner to delete
        if (!$user->hasRole('admin') && $appointment->user_id !== $user->id) {
            return ResponseHelper::error('Unauthorized to delete this appointment', 403);
        }

        $appointment->delete();

        return ResponseHelper::success('Appointment deleted successfully');
    }

    /**
     * Get user's upcoming appointments
     */
    public function upcoming(): JsonResponse
    {
        $user = auth()->user();
        
        $appointments = Appointment::with(['doctor.user', 'pet'])
            ->where('user_id', $user->id)
            ->upcoming()
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_datetime', 'asc')
            ->limit(10)
            ->get();

        $appointmentResources = $appointments->map(function ($appointment) {
            return AppointmentResource::fromModel($appointment);
        });

        return ResponseHelper::success('Upcoming appointments retrieved successfully', [
            'appointments' => $appointmentResources,
            'total' => $appointments->count()
        ]);
    }

    /**
     * Get appointment history
     */
    public function history(): JsonResponse
    {
        $user = auth()->user();
        
        $appointments = Appointment::with(['doctor.user', 'pet'])
            ->where('user_id', $user->id)
            ->past()
            ->orderBy('start_datetime', 'desc')
            ->paginate(15);

        $appointmentResources = $appointments->getCollection()->map(function ($appointment) {
            return AppointmentResource::fromModel($appointment);
        });

        return ResponseHelper::success('Appointment history retrieved successfully', [
            'appointments' => $appointmentResources,
            'pagination' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ]
        ]);
    }
}