<?php

namespace App\Services;

use App\Data\Appointments\BookAppointmentData;
use App\Data\Appointments\UpdateAppointmentData;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentBookingService
{
    public function __construct(
        private readonly DoctorAvailabilityService $availabilityService
    ) {
    }

    /**
     * Book a new appointment
     */
    public function bookAppointment(BookAppointmentData $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            // Validate pet ownership
            $this->validatePetOwnership($data->pet_id, $user->id);

            // Validate doctor availability
            $this->validateDoctorAvailability($data);

            // Create the appointment
            $appointment = $this->createAppointment($data, $user);

            return [
                'success' => true,
                'appointment' => $appointment,
                'message' => 'Appointment booked successfully'
            ];
        });
    }

    /**
     * Update an existing appointment
     */
    public function updateAppointment(Appointment $appointment, UpdateAppointmentData $data, User $user): array
    {
        return DB::transaction(function () use ($appointment, $data, $user) {
            // Check if user owns this appointment
            $this->validateAppointmentOwnership($appointment, $user);

            // If updating pet, validate ownership
            if ($data->pet_id && $data->pet_id !== $appointment->pet_id) {
                $this->validatePetOwnership($data->pet_id, $user->id);
            }

            // If updating time/date/doctor, validate availability
            if ($this->isTimeOrDoctorChange($data, $appointment)) {
                $this->validateDoctorAvailabilityForUpdate($data, $appointment);
            }

            // Update the appointment
            $updatedAppointment = $this->performUpdate($appointment, $data);

            return [
                'success' => true,
                'appointment' => $updatedAppointment,
                'message' => 'Appointment updated successfully'
            ];
        });
    }

    /**
     * Cancel an appointment
     */
    public function cancelAppointment(Appointment $appointment, User $user): array
    {
        $this->validateAppointmentOwnership($appointment, $user);

        // Check if appointment can be cancelled (not in the past, not already completed)
        if ($appointment->start_datetime->isPast()) {
            throw new \InvalidArgumentException('Cannot cancel past appointments');
        }

        if (in_array($appointment->status, ['completed', 'cancelled'])) {
            throw new \InvalidArgumentException('Appointment is already ' . $appointment->status);
        }

        $appointment->update(['status' => 'cancelled']);

        return [
            'success' => true,
            'appointment' => $appointment->fresh(),
            'message' => 'Appointment cancelled successfully'
        ];
    }

    /**
     * Get user's appointments with filtering
     */
    public function getUserAppointments(User $user, array $filters = []): Collection
    {
        $query = Appointment::with(['doctor.user', 'pet'])
            ->where('user_id', $user->id);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('start_datetime', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('start_datetime', '<=', $filters['end_date']);
        }

        if (!empty($filters['doctor_id'])) {
            $query->where('doctor_id', $filters['doctor_id']);
        }

        if (!empty($filters['pet_id'])) {
            $query->where('pet_id', $filters['pet_id']);
        }

        if (!empty($filters['appointment_type'])) {
            $query->where('appointment_type', $filters['appointment_type']);
        }

        return $query->orderBy('start_datetime', 'asc')->get();
    }

    /**
     * Check if the requested time slot is available
     */
    public function isSlotAvailable(int $doctorId, Carbon $startTime, Carbon $endTime, ?int $excludeAppointmentId = null): bool
    {
        $doctor = Doctor::findOrFail($doctorId);
        
        // Check if doctor is working at this time
        if (!$this->isDoctorWorkingAtTime($doctor, $startTime)) {
            return false;
        }

        // Check for conflicting appointments
        $conflictingAppointments = Appointment::where('doctor_id', $doctorId)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_datetime', [$startTime, $endTime])
                    ->orWhereBetween('end_datetime', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_datetime', '<', $startTime)
                          ->where('end_datetime', '>', $endTime);
                    });
            });

        if ($excludeAppointmentId) {
            $conflictingAppointments->where('id', '!=', $excludeAppointmentId);
        }

        return $conflictingAppointments->count() === 0;
    }

    private function validatePetOwnership(int $petId, int $userId): void
    {
        $pet = Pet::where('id', $petId)->where('owner_id', $userId)->first();
        
        if (!$pet) {
            throw new \InvalidArgumentException('Pet not found or you do not own this pet');
        }
    }

    private function validateAppointmentOwnership(Appointment $appointment, User $user): void
    {
        if ($appointment->user_id !== $user->id) {
            throw new \InvalidArgumentException('You do not own this appointment');
        }
    }

    private function validateDoctorAvailability(BookAppointmentData $data): void
    {
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $data->date . ' ' . $data->time);
        $endTime = $startTime->copy()->addMinutes($data->duration);

        if (!$this->isSlotAvailable($data->doctor_id, $startTime, $endTime)) {
            throw new \InvalidArgumentException('The selected time slot is not available');
        }
    }

    private function validateDoctorAvailabilityForUpdate(UpdateAppointmentData $data, Appointment $appointment): void
    {
        $doctorId = $data->doctor_id ?? $appointment->doctor_id;
        
        $startTime = $appointment->start_datetime;
        if ($data->date || $data->time) {
            $date = $data->date ?? $appointment->start_datetime->format('Y-m-d');
            $time = $data->time ?? $appointment->start_datetime->format('H:i');
            $startTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
        }

        $duration = $data->duration ?? $appointment->duration;
        $endTime = $startTime->copy()->addMinutes($duration);

        if (!$this->isSlotAvailable($doctorId, $startTime, $endTime, $appointment->id)) {
            throw new \InvalidArgumentException('The selected time slot is not available');
        }
    }

    private function isDoctorWorkingAtTime(Doctor $doctor, Carbon $time): bool
    {
        if (!$doctor->working_hours || !str_contains($doctor->working_hours, '-')) {
            return false;
        }

        [$startHour, $endHour] = explode('-', $doctor->working_hours);
        $workStart = $time->copy()->setTimeFromTimeString($startHour);
        $workEnd = $time->copy()->setTimeFromTimeString($endHour);

        return $time->between($workStart, $workEnd);
    }

    private function createAppointment(BookAppointmentData $data, User $user): Appointment
    {
        $startTime = Carbon::createFromFormat('Y-m-d H:i', $data->date . ' ' . $data->time);
        $endTime = $startTime->copy()->addMinutes($data->duration);

        return Appointment::create([
            'doctor_id' => $data->doctor_id,
            'user_id' => $user->id,
            'pet_id' => $data->pet_id,
            'start_datetime' => $startTime,
            'end_datetime' => $endTime,
            'appointment_type' => $data->appointment_type,
            'duration' => $data->duration,
            'notes' => $data->notes,
            'status' => 'pending',
        ]);
    }

    private function isTimeOrDoctorChange(UpdateAppointmentData $data, Appointment $appointment): bool
    {
        return $data->doctor_id !== null ||
               $data->date !== null ||
               $data->time !== null ||
               $data->duration !== null;
    }

    private function performUpdate(Appointment $appointment, UpdateAppointmentData $data): Appointment
    {
        $updateData = [];

        if ($data->doctor_id !== null) {
            $updateData['doctor_id'] = $data->doctor_id;
        }

        if ($data->pet_id !== null) {
            $updateData['pet_id'] = $data->pet_id;
        }

        if ($data->appointment_type !== null) {
            $updateData['appointment_type'] = $data->appointment_type;
        }

        if ($data->status !== null) {
            $updateData['status'] = $data->status;
        }

        if ($data->notes !== null) {
            $updateData['notes'] = $data->notes;
        }

        // Handle time/date changes
        if ($data->date !== null || $data->time !== null || $data->duration !== null) {
            $date = $data->date ?? $appointment->start_datetime->format('Y-m-d');
            $time = $data->time ?? $appointment->start_datetime->format('H:i');
            $duration = $data->duration ?? $appointment->duration;

            $startTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
            $endTime = $startTime->copy()->addMinutes($duration);

            $updateData['start_datetime'] = $startTime;
            $updateData['end_datetime'] = $endTime;
            $updateData['duration'] = $duration;
        }

        $appointment->update($updateData);

        return $appointment->fresh();
    }
} 