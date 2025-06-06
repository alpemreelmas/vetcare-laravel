<?php

namespace App\Http\Controllers\Admin;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\Appointment;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * Display a listing of all invoices (Admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['pet.owner', 'doctor.user', 'appointment', 'items.service']);

        // Apply filters
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }

        if ($request->has('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

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

        if ($request->has('overdue') && $request->boolean('overdue')) {
            $query->overdue();
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

        return ResponseHelper::success('Invoices retrieved successfully', [
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
     * Store a newly created invoice (Admin can create for any appointment).
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'appointment_id' => 'nullable|exists:appointments,id',
            'pet_id' => 'required|exists:pets,id',
            'owner_id' => 'required|exists:users,id',
            'doctor_id' => 'nullable|exists:doctors,id',
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
                'doctor_id' => $validatedData['doctor_id'] ?? null,
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

            $invoice->load(['pet.owner', 'doctor.user', 'items.service']);

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
     * Display the specified invoice (Admin can view any invoice).
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'pet.owner',
            'doctor.user',
            'appointment',
            'items.service',
            'payments.user'
        ]);

        return ResponseHelper::success('Invoice retrieved successfully', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Update the specified invoice (Admin can update any invoice).
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
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
            'status' => 'nullable|in:draft,sent,viewed,paid,partially_paid,overdue,cancelled,refunded',
        ]);

        $invoice->update($validatedData);
        $invoice->calculateTotals();
        $invoice->save();

        $invoice->load(['pet.owner', 'doctor.user', 'items.service']);

        return ResponseHelper::success('Invoice updated successfully', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Remove the specified invoice (Admin only).
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        // Check if invoice has payments
        if ($invoice->payments()->exists()) {
            return ResponseHelper::error('Cannot delete invoice with payments', 422);
        }

        $invoice->delete();

        return ResponseHelper::success('Invoice deleted successfully');
    }

    /**
     * Create invoice from appointment (Admin only).
     */
    public function createFromAppointment(Appointment $appointment): JsonResponse
    {
        // Check if appointment already has an invoice
        if ($appointment->invoice()->exists()) {
            return ResponseHelper::error('Appointment already has an invoice', 422);
        }

        // Get default services based on appointment type
        $defaultServices = $this->getDefaultServicesForAppointment($appointment);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'appointment_id' => $appointment->id,
            'pet_id' => $appointment->pet_id,
            'owner_id' => $appointment->user_id,
            'doctor_id' => $appointment->doctor_id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'service_date' => $appointment->start_datetime->toDateString(),
            'tax_rate' => 0,
            'subtotal' => 0,
            'total_amount' => 0,
            'balance_due' => 0,
        ]);

        // Add default services
        foreach ($defaultServices as $service) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'description' => $service->description,
                'service_code' => $service->service_code,
                'quantity' => 1,
                'unit_price' => $service->base_price,
                'total_price' => $service->base_price,
            ]);
        }

        $invoice->calculateTotals();
        $invoice->save();

        $invoice->load(['pet.owner', 'doctor.user', 'items.service']);

        return ResponseHelper::success('Invoice created from appointment successfully', [
            'invoice' => $invoice
        ], 201);
    }

    /**
     * Get invoice statistics (Admin only).
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_invoices' => Invoice::count(),
            'total_revenue' => Invoice::where('payment_status', 'paid')->sum('total_amount'),
            'pending_amount' => Invoice::whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('balance_due'),
            'overdue_invoices' => Invoice::overdue()->count(),
            'overdue_amount' => Invoice::overdue()->sum('balance_due'),
            'invoices_by_status' => Invoice::selectRaw('status, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('status')
                ->get(),
            'payment_status_breakdown' => Invoice::selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('payment_status')
                ->get(),
            'monthly_revenue' => Invoice::selectRaw('YEAR(invoice_date) as year, MONTH(invoice_date) as month, SUM(total_amount) as revenue')
                ->where('payment_status', 'paid')
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
            'top_services' => InvoiceItem::selectRaw('service_name, SUM(quantity) as total_quantity, SUM(total_price) as total_revenue')
                ->groupBy('service_name')
                ->orderBy('total_revenue', 'desc')
                ->limit(10)
                ->get(),
        ];

        return ResponseHelper::success('Invoice statistics retrieved successfully', $stats);
    }

    /**
     * Send invoice to customer (Admin only).
     */
    public function sendInvoice(Invoice $invoice): JsonResponse
    {
        // Here you would integrate with email service
        // For now, just mark as sent
        $invoice->markAsSent();

        return ResponseHelper::success('Invoice sent successfully', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Mark invoice as viewed (when customer opens it).
     */
    public function markAsViewed(Invoice $invoice): JsonResponse
    {
        $invoice->markAsViewed();

        return ResponseHelper::success('Invoice marked as viewed', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Get default services for appointment type.
     */
    private function getDefaultServicesForAppointment(Appointment $appointment): array
    {
        // This is a simple implementation - you can make it more sophisticated
        $defaultServiceNames = match($appointment->appointment_type) {
            'checkup' => ['General Checkup'],
            'vaccination' => ['Vaccination'],
            'surgery' => ['Surgery Consultation'],
            'emergency' => ['Emergency Consultation'],
            default => ['General Consultation']
        };

        return Service::whereIn('name', $defaultServiceNames)->active()->get()->toArray();
    }
}
