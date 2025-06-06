<?php

namespace App\Http\Controllers\Doctor;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Display a listing of doctor's invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $query = Invoice::with(['pet.owner', 'appointment', 'items.service'])
            ->where('doctor_id', $doctor->id);

        // Apply filters
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->pet_id);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('payment_status')) {
            $query->byPaymentStatus($request->payment_status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                  ->orWhereHas('pet', function ($petQuery) use ($search) {
                      $petQuery->where('name', 'like', '%' . $search . '%');
                  })
                  ->orWhereHas('owner', function ($ownerQuery) use ($search) {
                      $ownerQuery->where('name', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $invoices = $query->orderBy('invoice_date', 'desc')->paginate($perPage);

        return ResponseHelper::success('Your invoices retrieved successfully', [
            'invoices' => $invoices->items(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ]
        ]);
    }

    /**
     * Store a newly created invoice (Doctor can create for their patients).
     */
    public function store(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $validatedData = $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'pet_id' => 'required|exists:pets,id',
            'owner_id' => 'required|exists:users,id',
            'service_date' => 'nullable|date',
            'due_date' => 'nullable|date|after:today',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'payment_instructions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.service_id' => 'required|exists:services,id',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.discount_reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Create invoice
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'appointment_id' => $validatedData['appointment_id'] ?? null,
                'pet_id' => $validatedData['pet_id'],
                'owner_id' => $validatedData['owner_id'],
                'doctor_id' => $doctor->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => $validatedData['due_date'] ?? now()->addDays(30)->toDateString(),
                'service_date' => $validatedData['service_date'] ?? now()->toDateString(),
                'tax_rate' => $validatedData['tax_rate'] ?? 0,
                'discount_type' => $validatedData['discount_type'] ?? null,
                'discount_value' => $validatedData['discount_value'] ?? 0,
                'discount_reason' => $validatedData['discount_reason'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'terms_and_conditions' => $validatedData['terms_and_conditions'] ?? null,
                'payment_instructions' => $validatedData['payment_instructions'] ?? null,
                'subtotal' => 0,
                'total_amount' => 0,
                'balance_due' => 0,
            ]);

            // Create invoice items
            foreach ($validatedData['items'] as $itemData) {
                $service = Service::findOrFail($itemData['service_id']);
                $quantity = $itemData['quantity'] ?? 1;
                $unitPrice = $itemData['unit_price'] ?? $service->base_price;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'description' => $service->description,
                    'service_code' => $service->service_code,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'discount_amount' => $itemData['discount_amount'] ?? 0,
                    'discount_reason' => $itemData['discount_reason'] ?? null,
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            // Calculate totals
            $invoice->calculateTotals();
            $invoice->save();

            $invoice->load(['pet.owner', 'items.service']);

            DB::commit();

            return ResponseHelper::success('Invoice created successfully', [
                'invoice' => $invoice
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to create invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified invoice (only if doctor owns it).
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if doctor owns this invoice
        if ($invoice->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only view your own invoices', 403);
        }

        $invoice->load([
            'pet.owner',
            'appointment',
            'items.service',
            'payments.user'
        ]);

        return ResponseHelper::success('Invoice retrieved successfully', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Update the specified invoice (only if doctor owns it).
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if doctor owns this invoice
        if ($invoice->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only update your own invoices', 403);
        }

        // Don't allow updates if invoice has payments
        if ($invoice->payments()->exists()) {
            return ResponseHelper::error('Cannot update invoice with payments', 422);
        }

        $validatedData = $request->validate([
            'due_date' => 'nullable|date',
            'service_date' => 'nullable|date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_type' => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'payment_instructions' => 'nullable|string',
        ]);

        $invoice->update($validatedData);
        $invoice->calculateTotals();
        $invoice->save();

        $invoice->load(['pet.owner', 'items.service']);

        return ResponseHelper::success('Invoice updated successfully', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Get doctor's invoice statistics.
     */
    public function statistics(): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $stats = [
            'total_invoices' => Invoice::where('doctor_id', $doctor->id)->count(),
            'total_revenue' => Invoice::where('doctor_id', $doctor->id)
                ->where('payment_status', 'paid')->sum('total_amount'),
            'pending_amount' => Invoice::where('doctor_id', $doctor->id)
                ->whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('balance_due'),
            'overdue_invoices' => Invoice::where('doctor_id', $doctor->id)->overdue()->count(),
            'invoices_by_status' => Invoice::where('doctor_id', $doctor->id)
                ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('payment_status')
                ->get(),
            'monthly_revenue' => Invoice::where('doctor_id', $doctor->id)
                ->selectRaw('YEAR(invoice_date) as year, MONTH(invoice_date) as month, SUM(total_amount) as revenue')
                ->where('payment_status', 'paid')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get(),
            'top_services' => InvoiceItem::whereHas('invoice', function ($query) use ($doctor) {
                    $query->where('doctor_id', $doctor->id);
                })
                ->selectRaw('service_name, SUM(quantity) as total_quantity, SUM(total_price) as total_revenue')
                ->groupBy('service_name')
                ->orderBy('total_revenue', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ResponseHelper::success('Doctor invoice statistics retrieved successfully', $stats);
    }
}
