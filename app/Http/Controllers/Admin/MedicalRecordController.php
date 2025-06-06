<?php

namespace App\Http\Controllers\Admin;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\Pet;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicalRecordController extends Controller
{
    /**
     * Display a listing of all medical records (Admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = MedicalRecord::with(['pet.owner', 'doctor.user', 'appointment', 'diagnoses', 'treatments']);

        // Apply filters
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->pet_id);
        }

        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
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
                  })
                  ->orWhereHas('pet.owner', function ($ownerQuery) use ($search) {
                      $ownerQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $records = $query->orderBy('visit_date', 'desc')->paginate($perPage);

        return ResponseHelper::success('Medical records retrieved successfully', [
            'medical_records' => $records->items(),
            'pagination' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
            'filters_applied' => $request->only(['pet_id', 'doctor_id', 'status', 'start_date', 'end_date', 'search'])
        ]);
    }

    /**
     * Store a newly created medical record (Admin can create for any appointment).
     */
    public function store(Request $request): JsonResponse
    {
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

        // Check if medical record already exists for this appointment
        if ($appointment->hasMedicalRecord()) {
            return ResponseHelper::error('Medical record already exists for this appointment', 422);
        }

        DB::beginTransaction();
        try {
            $medicalRecord = MedicalRecord::create(array_merge($validatedData, [
                'pet_id' => $appointment->pet_id,
                'doctor_id' => $appointment->doctor_id,
                'visit_date' => $appointment->start_datetime->toDateString(),
            ]));

            $medicalRecord->load(['pet.owner', 'doctor.user', 'appointment']);

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
     * Display the specified medical record (Admin can view any record).
     */
    public function show(MedicalRecord $medicalRecord): JsonResponse
    {
        $medicalRecord->load([
            'pet.owner',
            'doctor.user',
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
     * Update the specified medical record (Admin can update any record).
     */
    public function update(Request $request, MedicalRecord $medicalRecord): JsonResponse
    {
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
        $medicalRecord->load(['pet.owner', 'doctor.user', 'appointment', 'diagnoses', 'treatments']);

        return ResponseHelper::success('Medical record updated successfully', [
            'medical_record' => $medicalRecord
        ]);
    }

    /**
     * Remove the specified medical record (Admin only).
     */
    public function destroy(MedicalRecord $medicalRecord): JsonResponse
    {
        $medicalRecord->delete();

        return ResponseHelper::success('Medical record deleted successfully');
    }

    /**
     * Get medical records statistics (Admin only).
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_records' => MedicalRecord::count(),
            'records_by_status' => MedicalRecord::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
            'records_this_month' => MedicalRecord::whereMonth('visit_date', now()->month)
                ->whereYear('visit_date', now()->year)
                ->count(),
            'records_by_doctor' => MedicalRecord::with('doctor.user')
                ->selectRaw('doctor_id, COUNT(*) as count')
                ->groupBy('doctor_id')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'recent_records' => MedicalRecord::with(['pet', 'doctor.user'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ResponseHelper::success('Medical records statistics retrieved successfully', $stats);
    }

    /**
     * Get medical records by doctor (Admin only).
     */
    public function byDoctor(Doctor $doctor): JsonResponse
    {
        $records = MedicalRecord::where('doctor_id', $doctor->id)
            ->with(['pet.owner', 'appointment'])
            ->orderBy('visit_date', 'desc')
            ->paginate(15);

        return ResponseHelper::success('Doctor medical records retrieved successfully', [
            'doctor' => $doctor->load('user'),
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
     * Get medical records by pet (Admin only).
     */
    public function byPet(Pet $pet): JsonResponse
    {
        $records = $pet->medicalRecords()
            ->with(['doctor.user', 'appointment', 'diagnoses', 'treatments'])
            ->orderBy('visit_date', 'desc')
            ->get();

        $summary = $pet->getMedicalHistorySummary();

        return ResponseHelper::success('Pet medical records retrieved successfully', [
            'pet' => $pet->load('owner'),
            'medical_records' => $records,
            'summary' => $summary
        ]);
    }

    /**
     * Bulk update medical record statuses (Admin only).
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'record_ids' => 'required|array',
            'record_ids.*' => 'integer|exists:medical_records,id',
            'status' => 'required|in:draft,completed,reviewed',
        ]);

        $updated = MedicalRecord::whereIn('id', $request->record_ids)
            ->update(['status' => $request->status]);

        return ResponseHelper::success("Updated {$updated} medical records to {$request->status} status", [
            'updated_count' => $updated,
            'new_status' => $request->status
        ]);
    }
}
