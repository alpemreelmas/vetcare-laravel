<?php

namespace App\Http\Controllers\Doctor;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Treatment;
use App\Models\MedicalRecord;
use App\Models\Diagnosis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentController extends Controller
{
    /**
     * Display a listing of treatments for doctor's patients.
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $query = Treatment::with(['pet.owner', 'medicalRecord', 'diagnosis'])
            ->whereHas('medicalRecord', function ($q) use ($doctor) {
                $q->where('doctor_id', $doctor->id);
            });

        // Apply filters
        if ($request->has('pet_id')) {
            $query->forPet($request->pet_id);
        }

        if ($request->has('medical_record_id')) {
            $query->where('medical_record_id', $request->medical_record_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('payable')) {
            if ($request->boolean('payable')) {
                $query->payable();
            }
        }

        if ($request->has('billed')) {
            if ($request->boolean('billed')) {
                $query->billed();
            }
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        $perPage = $request->get('per_page', 15);
        $treatments = $query->orderBy('start_date', 'desc')->paginate($perPage);

        return ResponseHelper::success('Treatments retrieved successfully', [
            'treatments' => $treatments->items(),
            'pagination' => [
                'current_page' => $treatments->currentPage(),
                'last_page' => $treatments->lastPage(),
                'per_page' => $treatments->perPage(),
                'total' => $treatments->total(),
            ]
        ]);
    }

    /**
     * Store a newly created treatment.
     */
    public function store(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $validatedData = $request->validate([
            'medical_record_id' => 'required|exists:medical_records,id',
            'diagnosis_id' => 'nullable|exists:diagnoses,id',
            'type' => 'required|in:medication,procedure,surgery,therapy,vaccination,diagnostic_test,other',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'medication_name' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'frequency' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'duration_days' => 'nullable|integer|min:1',
            'procedure_code' => 'nullable|string|max:255',
            'procedure_notes' => 'nullable|string',
            'anesthesia_type' => 'nullable|in:none,local,general,sedation',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:prescribed,in_progress,completed,discontinued,on_hold',
            'instructions' => 'nullable|string',
            'side_effects' => 'nullable|string',
            'response_notes' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'billing_code' => 'nullable|string|max:255',
        ]);

        $medicalRecord = MedicalRecord::findOrFail($request->medical_record_id);

        // Check if this medical record belongs to the doctor
        if ($medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only add treatments to your own medical records', 403);
        }

        DB::beginTransaction();
        try {
            $treatment = Treatment::create(array_merge($validatedData, [
                'pet_id' => $medicalRecord->pet_id,
            ]));

            $treatment->load(['pet.owner', 'medicalRecord', 'diagnosis']);

            // Get billing information if treatment is payable
            $billingInfo = null;
            if ($treatment->isPayable()) {
                $invoice = $treatment->invoice();
                if ($invoice) {
                    $billingInfo = [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $treatment->cost,
                        'is_billed' => true,
                    ];
                }
            }

            DB::commit();

            return ResponseHelper::success('Treatment created successfully', [
                'treatment' => $treatment,
                'billing_info' => $billingInfo,
                'message' => $billingInfo ? 'Treatment created and invoice generated automatically' : 'Treatment created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to create treatment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified treatment.
     */
    public function show(Treatment $treatment): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this treatment belongs to doctor's medical record
        if ($treatment->medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('Treatment not found', 404);
        }

        $treatment->load(['pet.owner', 'medicalRecord', 'diagnosis']);

        // Get billing information
        $billingInfo = null;
        if ($treatment->isPayable()) {
            $invoice = $treatment->invoice();
            if ($invoice) {
                $billingInfo = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'amount' => $treatment->cost,
                    'is_billed' => true,
                    'payment_status' => $invoice->payment_status,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'balance_due' => $invoice->balance_due,
                ];
            }
        }

        return ResponseHelper::success('Treatment retrieved successfully', [
            'treatment' => $treatment,
            'billing_info' => $billingInfo
        ]);
    }

    /**
     * Update the specified treatment.
     */
    public function update(Request $request, Treatment $treatment): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this treatment belongs to doctor's medical record
        if ($treatment->medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only update your own treatments', 403);
        }

        $validatedData = $request->validate([
            'diagnosis_id' => 'nullable|exists:diagnoses,id',
            'type' => 'sometimes|in:medication,procedure,surgery,therapy,vaccination,diagnostic_test,other',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'medication_name' => 'nullable|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'frequency' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'duration_days' => 'nullable|integer|min:1',
            'procedure_code' => 'nullable|string|max:255',
            'procedure_notes' => 'nullable|string',
            'anesthesia_type' => 'nullable|in:none,local,general,sedation',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:prescribed,in_progress,completed,discontinued,on_hold',
            'instructions' => 'nullable|string',
            'side_effects' => 'nullable|string',
            'response_notes' => 'nullable|string',
            'cost' => 'nullable|numeric|min:0',
            'billing_code' => 'nullable|string|max:255',
        ]);

        // Check if invoice exists and has payments before allowing cost changes
        if (isset($validatedData['cost']) && $treatment->isBilled()) {
            $invoice = $treatment->invoice();
            if ($invoice && $invoice->payments()->exists()) {
                return ResponseHelper::error('Cannot change cost of treatment that has been paid', 422);
            }
        }

        DB::beginTransaction();
        try {
            $oldCost = $treatment->cost;
            $treatment->update($validatedData);
            $treatment->load(['pet.owner', 'medicalRecord', 'diagnosis']);

            // Get updated billing information
            $billingInfo = null;
            $billingMessage = '';
            
            if ($treatment->isPayable()) {
                $invoice = $treatment->invoice();
                if ($invoice) {
                    $billingInfo = [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => $treatment->cost,
                        'is_billed' => true,
                        'payment_status' => $invoice->payment_status,
                    ];
                    
                    if (isset($validatedData['cost']) && $oldCost != $treatment->cost) {
                        $billingMessage = ' and invoice updated automatically';
                    }
                } else {
                    $billingMessage = ' and invoice created automatically';
                }
            }

            DB::commit();

            return ResponseHelper::success('Treatment updated successfully' . $billingMessage, [
                'treatment' => $treatment,
                'billing_info' => $billingInfo
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to update treatment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified treatment.
     */
    public function destroy(Treatment $treatment): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this treatment belongs to doctor's medical record
        if ($treatment->medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only delete your own treatments', 403);
        }

        // Check if treatment has been paid
        if ($treatment->isBilled()) {
            $invoice = $treatment->invoice();
            if ($invoice && $invoice->payments()->exists()) {
                return ResponseHelper::error('Cannot delete treatment that has been paid', 422);
            }
        }

        $treatment->delete();

        return ResponseHelper::success('Treatment deleted successfully');
    }

    /**
     * Get treatments for a specific medical record.
     */
    public function byMedicalRecord(MedicalRecord $medicalRecord): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this medical record belongs to the doctor
        if ($medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('Medical record not found', 404);
        }

        $treatments = $medicalRecord->treatments()
            ->with(['diagnosis'])
            ->orderBy('start_date', 'desc')
            ->get();

        // Add billing information to each treatment
        $treatmentsWithBilling = $treatments->map(function ($treatment) {
            $treatmentArray = $treatment->toArray();
            
            if ($treatment->isPayable()) {
                $invoice = $treatment->invoice();
                $treatmentArray['billing_info'] = $invoice ? [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'is_billed' => true,
                    'payment_status' => $invoice->payment_status,
                ] : null;
            } else {
                $treatmentArray['billing_info'] = null;
            }
            
            return $treatmentArray;
        });

        return ResponseHelper::success('Medical record treatments retrieved successfully', [
            'medical_record' => $medicalRecord->only(['id', 'visit_date']),
            'treatments' => $treatmentsWithBilling
        ]);
    }

    /**
     * Mark treatment as administered.
     */
    public function markAsAdministered(Treatment $treatment): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this treatment belongs to doctor's medical record
        if ($treatment->medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only update your own treatments', 403);
        }

        $treatment->markAsAdministered();
        $treatment->load(['pet.owner', 'medicalRecord', 'diagnosis']);

        return ResponseHelper::success('Treatment marked as administered', [
            'treatment' => $treatment
        ]);
    }

    /**
     * Mark treatment as completed.
     */
    public function markAsCompleted(Treatment $treatment): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this treatment belongs to doctor's medical record
        if ($treatment->medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only update your own treatments', 403);
        }

        $treatment->markAsCompleted();
        $treatment->load(['pet.owner', 'medicalRecord', 'diagnosis']);

        return ResponseHelper::success('Treatment marked as completed', [
            'treatment' => $treatment
        ]);
    }
} 