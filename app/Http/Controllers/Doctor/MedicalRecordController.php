<?php

namespace App\Http\Controllers\Doctor;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicalRecordController extends Controller
{
    /**
     * Display medical records for the authenticated doctor.
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $query = MedicalRecord::where('doctor_id', $doctor->id)
            ->with(['pet.owner', 'appointment', 'diagnoses', 'treatments']);

        // Apply filters
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->pet_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('visit_date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('chief_complaint', 'like', '%' . $search . '%')
                  ->orWhere('assessment', 'like', '%' . $search . '%')
                  ->orWhere('notes', 'like', '%' . $search . '%')
                  ->orWhereHas('pet', function ($petQuery) use ($search) {
                      $petQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $records = $query->orderBy('visit_date', 'desc')->paginate($perPage);

        return ResponseHelper::success('Your medical records retrieved successfully', [
            'medical_records' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ]
        ]);
    }

    /**
     * Store a newly created medical record for doctor's appointment.
     */
    public function store(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $validatedData = $request->validate([
            'appointment_id' => 'required|exists:appointments,id',
            'chief_complaint' => 'nullable|string',
            'history_of_present_illness' => 'nullable|string',
            'physical_examination' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0',
            'temperature' => 'nullable|numeric|min:0|max:50',
            'heart_rate' => 'nullable|integer|min:0|max:500',
            'respiratory_rate' => 'nullable|integer|min:0|max:200',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'notes' => 'nullable|string',
            'follow_up_instructions' => 'nullable|string',
            'next_visit_date' => 'nullable|date|after:today',
            'status' => 'nullable|in:draft,completed,reviewed',
        ]);

        $appointment = Appointment::findOrFail($request->appointment_id);

        // Check if this appointment belongs to the doctor
        if ($appointment->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only create medical records for your own appointments', 403);
        }

        // Check if medical record already exists for this appointment
        if ($appointment->hasMedicalRecord()) {
            return ResponseHelper::error('Medical record already exists for this appointment', 422);
        }

        DB::beginTransaction();
        try {
            $medicalRecord = MedicalRecord::create(array_merge($validatedData, [
                'pet_id' => $appointment->pet_id,
                'doctor_id' => $doctor->id,
                'visit_date' => $appointment->start_datetime->toDateString(),
            ]));

            $medicalRecord->load(['pet.owner', 'appointment']);

            DB::commit();

            return ResponseHelper::success('Medical record created successfully', [
                'medical_record' => $medicalRecord
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to create medical record: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified medical record (only doctor's own records).
     */
    public function show(MedicalRecord $medicalRecord): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this record belongs to the doctor
        if ($medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('Medical record not found', 404);
        }

        $medicalRecord->load([
            'pet.owner',
            'appointment',
            'diagnoses',
            'treatments',
            'documents'
        ]);

        return ResponseHelper::success('Medical record retrieved successfully', [
            'medical_record' => $medicalRecord
        ]);
    }

    /**
     * Update the specified medical record (only doctor's own records).
     */
    public function update(Request $request, MedicalRecord $medicalRecord): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this record belongs to the doctor
        if ($medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only update your own medical records', 403);
        }

        $validatedData = $request->validate([
            'chief_complaint' => 'nullable|string',
            'history_of_present_illness' => 'nullable|string',
            'physical_examination' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0',
            'temperature' => 'nullable|numeric|min:0|max:50',
            'heart_rate' => 'nullable|integer|min:0|max:500',
            'respiratory_rate' => 'nullable|integer|min:0|max:200',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'notes' => 'nullable|string',
            'follow_up_instructions' => 'nullable|string',
            'next_visit_date' => 'nullable|date|after:today',
            'status' => 'nullable|in:draft,completed,reviewed',
        ]);

        $medicalRecord->update($validatedData);
        $medicalRecord->load(['pet.owner', 'appointment', 'diagnoses', 'treatments']);

        return ResponseHelper::success('Medical record updated successfully', [
            'medical_record' => $medicalRecord
        ]);
    }

    /**
     * Get medical history for a pet (only if doctor has treated the pet).
     */
    public function petHistory(Pet $pet): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if doctor has treated this pet
        $hasAccess = MedicalRecord::where('pet_id', $pet->id)
            ->where('doctor_id', $doctor->id)
            ->exists();

        if (!$hasAccess) {
            return ResponseHelper::error('You can only view medical history for pets you have treated', 403);
        }

        $medicalRecords = $pet->medicalRecords()
            ->with(['doctor.user', 'appointment', 'diagnoses', 'treatments'])
            ->orderBy('visit_date', 'desc')
            ->get();

        $summary = $pet->getMedicalHistorySummary();

        return ResponseHelper::success('Pet medical history retrieved successfully', [
            'pet' => $pet->load('owner'),
            'medical_records' => $medicalRecords,
            'summary' => $summary
        ]);
    }

    /**
     * Create medical record from appointment (doctor's own appointment).
     */
    public function createFromAppointment(Appointment $appointment): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this appointment belongs to the doctor
        if ($appointment->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only create medical records for your own appointments', 403);
        }

        if ($appointment->hasMedicalRecord()) {
            return ResponseHelper::error('Medical record already exists for this appointment', 422);
        }

        $medicalRecord = $appointment->createMedicalRecord();
        $medicalRecord->load(['pet.owner', 'appointment']);

        return ResponseHelper::success('Medical record created successfully', [
            'medical_record' => $medicalRecord
        ], 201);
    }

    /**
     * Get doctor's upcoming appointments that need medical records.
     */
    public function pendingRecords(): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $appointments = Appointment::where('doctor_id', $doctor->id)
            ->where('status', 'completed')
            ->whereDoesntHave('medicalRecord')
            ->with(['pet.owner', 'user'])
            ->orderBy('start_datetime', 'desc')
            ->limit(20)
            ->get();

        return ResponseHelper::success('Pending medical records retrieved successfully', [
            'pending_appointments' => $appointments,
            'count' => $appointments->count()
        ]);
    }

    /**
     * Get doctor's medical records statistics.
     */
    public function myStatistics(): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $stats = [
            'total_records' => MedicalRecord::where('doctor_id', $doctor->id)->count(),
            'records_by_status' => MedicalRecord::where('doctor_id', $doctor->id)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
            'records_this_month' => MedicalRecord::where('doctor_id', $doctor->id)
                ->whereMonth('visit_date', now()->month)
                ->whereYear('visit_date', now()->year)
                ->count(),
            'pending_records' => Appointment::where('doctor_id', $doctor->id)
                ->where('status', 'completed')
                ->whereDoesntHave('medicalRecord')
                ->count(),
            'recent_records' => MedicalRecord::where('doctor_id', $doctor->id)
                ->with(['pet'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ResponseHelper::success('Your medical records statistics retrieved successfully', $stats);
    }
}
