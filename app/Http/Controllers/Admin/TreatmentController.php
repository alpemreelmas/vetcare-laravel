<?php

namespace App\Http\Controllers\Admin;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Treatment;
use App\Models\MedicalRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreatmentController extends Controller
{
    /**
     * Display a listing of all treatments (Admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Treatment::with(['pet.owner', 'medicalRecord.doctor.user', 'diagnosis']);

        // Apply filters
        if ($request->has('pet_id')) {
            $query->forPet($request->pet_id);
        }

        if ($request->has('doctor_id')) {
            $query->whereHas('medicalRecord', function ($q) use ($request) {
                $q->where('doctor_id', $request->doctor_id);
            });
        }

        if ($request->has('owner_id')) {
            $query->whereHas('pet', function ($q) use ($request) {
                $q->where('owner_id', $request->owner_id);
            });
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

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('medication_name', 'like', '%' . $search . '%')
                  ->orWhere('procedure_code', 'like', '%' . $search . '%')
                  ->orWhere('billing_code', 'like', '%' . $search . '%')
                  ->orWhereHas('pet', function ($petQuery) use ($search) {
                      $petQuery->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('pet.owner', function ($ownerQuery) use ($search) {
                      $ownerQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $treatments = $query->orderBy('start_date', 'desc')->paginate($perPage);

        // Add billing information to each treatment
        $treatmentsWithBilling = $treatments->getCollection()->map(function ($treatment) {
            $treatmentArray = $treatment->toArray();
            
            if ($treatment->isPayable()) {
                $invoice = $treatment->invoice();
                $treatmentArray['billing_info'] = $invoice ? [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'is_billed' => true,
                    'payment_status' => $invoice->payment_status,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'balance_due' => $invoice->balance_due,
                ] : null;
            } else {
                $treatmentArray['billing_info'] = null;
            }
            
            return $treatmentArray;
        });

        $treatments->setCollection($treatmentsWithBilling);

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
     * Store a newly created treatment (Admin can create for any medical record).
     */
    public function store(Request $request): JsonResponse
    {
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

        DB::beginTransaction();
        try {
            $treatment = Treatment::create(array_merge($validatedData, [
                'pet_id' => $medicalRecord->pet_id,
            ]));

            $treatment->load(['pet.owner', 'medicalRecord.doctor.user', 'diagnosis']);

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
     * Display the specified treatment (Admin can view any treatment).
     */
    public function show(Treatment $treatment): JsonResponse
    {
        $treatment->load(['pet.owner', 'medicalRecord.doctor.user', 'diagnosis']);

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
     * Update the specified treatment (Admin can update any treatment).
     */
    public function update(Request $request, Treatment $treatment): JsonResponse
    {
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

        DB::beginTransaction();
        try {
            $oldCost = $treatment->cost;
            $treatment->update($validatedData);
            $treatment->load(['pet.owner', 'medicalRecord.doctor.user', 'diagnosis']);

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
     * Remove the specified treatment (Admin only).
     */
    public function destroy(Treatment $treatment): JsonResponse
    {
        $treatment->delete();

        return ResponseHelper::success('Treatment deleted successfully');
    }

    /**
     * Get treatment statistics (Admin only).
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_treatments' => Treatment::count(),
            'payable_treatments' => Treatment::payable()->count(),
            'billed_treatments' => Treatment::billed()->count(),
            'total_treatment_revenue' => Treatment::payable()->sum('cost'),
            'treatments_by_type' => Treatment::selectRaw('type, COUNT(*) as count, SUM(cost) as total_cost')
                ->groupBy('type')
                ->get(),
            'treatments_by_status' => Treatment::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
            'monthly_treatments' => Treatment::selectRaw('YEAR(start_date) as year, MONTH(start_date) as month, COUNT(*) as count, SUM(cost) as total_cost')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
            'top_medications' => Treatment::where('type', 'medication')
                ->selectRaw('medication_name, COUNT(*) as count, SUM(cost) as total_cost')
                ->whereNotNull('medication_name')
                ->groupBy('medication_name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'top_procedures' => Treatment::whereIn('type', ['procedure', 'surgery'])
                ->selectRaw('name, COUNT(*) as count, SUM(cost) as total_cost')
                ->groupBy('name')
                ->orderBy('total_cost', 'desc')
                ->limit(10)
                ->get(),
        ];

        return ResponseHelper::success('Treatment statistics retrieved successfully', $stats);
    }

    /**
     * Get treatments by doctor.
     */
    public function byDoctor(Request $request): JsonResponse
    {
        $doctorId = $request->route('doctor');
        
        $treatments = Treatment::with(['pet.owner', 'medicalRecord', 'diagnosis'])
            ->whereHas('medicalRecord', function ($q) use ($doctorId) {
                $q->where('doctor_id', $doctorId);
            })
            ->orderBy('start_date', 'desc')
            ->get();

        // Add billing information
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

        return ResponseHelper::success('Doctor treatments retrieved successfully', [
            'treatments' => $treatmentsWithBilling
        ]);
    }

    /**
     * Get treatments by pet.
     */
    public function byPet(Request $request): JsonResponse
    {
        $petId = $request->route('pet');
        
        $treatments = Treatment::with(['medicalRecord.doctor.user', 'diagnosis'])
            ->forPet($petId)
            ->orderBy('start_date', 'desc')
            ->get();

        // Add billing information
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

        return ResponseHelper::success('Pet treatments retrieved successfully', [
            'treatments' => $treatmentsWithBilling
        ]);
    }

    /**
     * Bulk update treatment status.
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'treatment_ids' => 'required|array',
            'treatment_ids.*' => 'exists:treatments,id',
            'status' => 'required|in:prescribed,in_progress,completed,discontinued,on_hold',
        ]);

        $updatedCount = Treatment::whereIn('id', $validatedData['treatment_ids'])
            ->update(['status' => $validatedData['status']]);

        return ResponseHelper::success('Treatment status updated successfully', [
            'updated_count' => $updatedCount,
            'new_status' => $validatedData['status']
        ]);
    }
} 