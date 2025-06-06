<?php

namespace App\Http\Controllers\User;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Display a listing of user's invoices.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = Invoice::with(['pet', 'doctor.user', 'appointment', 'items.service'])
            ->where('owner_id', $user->id);

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

        if ($request->has('overdue') && $request->boolean('overdue')) {
            $query->overdue();
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
     * Display the specified invoice (only if user owns it).
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this invoice
        if ($invoice->owner_id !== $user->id) {
            return ResponseHelper::error('You can only view your own invoices', 403);
        }

        $invoice->load([
            'pet',
            'doctor.user',
            'appointment',
            'items.service',
            'payments'
        ]);

        // Mark invoice as viewed if not already viewed
        $invoice->markAsViewed();

        return ResponseHelper::success('Invoice retrieved successfully', [
            'invoice' => $invoice
        ]);
    }

    /**
     * Get user's invoice summary.
     */
    public function summary(): JsonResponse
    {
        $user = auth()->user();

        $summary = [
            'total_invoices' => Invoice::where('owner_id', $user->id)->count(),
            'total_amount' => Invoice::where('owner_id', $user->id)->sum('total_amount'),
            'paid_amount' => Invoice::where('owner_id', $user->id)->sum('paid_amount'),
            'outstanding_balance' => Invoice::where('owner_id', $user->id)->sum('balance_due'),
            'overdue_invoices' => Invoice::where('owner_id', $user->id)->overdue()->count(),
            'overdue_amount' => Invoice::where('owner_id', $user->id)->overdue()->sum('balance_due'),
            'invoices_by_status' => Invoice::where('owner_id', $user->id)
                ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total')
                ->groupBy('payment_status')
                ->get(),
            'recent_invoices' => Invoice::where('owner_id', $user->id)
                ->with(['pet', 'doctor.user'])
                ->orderBy('invoice_date', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ResponseHelper::success('Invoice summary retrieved successfully', $summary);
    }

    /**
     * Get invoices for a specific pet.
     */
    public function petInvoices(Request $request): JsonResponse
    {
        $user = auth()->user();
        $petId = $request->route('pet_id');

        // Verify pet ownership
        $pet = $user->pets()->findOrFail($petId);

        $query = Invoice::with(['doctor.user', 'appointment', 'items.service'])
            ->where('pet_id', $pet->id);

        if ($request->has('payment_status')) {
            $query->byPaymentStatus($request->payment_status);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->get();

        return ResponseHelper::success('Pet invoices retrieved successfully', [
            'pet' => $pet->only(['id', 'name', 'species', 'breed']),
            'invoices' => $invoices
        ]);
    }

    /**
     * Get overdue invoices for the user.
     */
    public function overdue(): JsonResponse
    {
        $user = auth()->user();

        $overdueInvoices = Invoice::with(['pet', 'doctor.user', 'items.service'])
            ->where('owner_id', $user->id)
            ->overdue()
            ->orderBy('due_date', 'asc')
            ->get();

        $totalOverdue = $overdueInvoices->sum('balance_due');

        return ResponseHelper::success('Overdue invoices retrieved successfully', [
            'overdue_invoices' => $overdueInvoices,
            'total_overdue_amount' => $totalOverdue,
            'count' => $overdueInvoices->count()
        ]);
    }

    /**
     * Get unpaid invoices for the user.
     */
    public function unpaid(): JsonResponse
    {
        $user = auth()->user();

        $unpaidInvoices = Invoice::with(['pet', 'doctor.user', 'items.service'])
            ->where('owner_id', $user->id)
            ->whereIn('payment_status', ['unpaid', 'partially_paid'])
            ->orderBy('due_date', 'asc')
            ->get();

        $totalUnpaid = $unpaidInvoices->sum('balance_due');

        return ResponseHelper::success('Unpaid invoices retrieved successfully', [
            'unpaid_invoices' => $unpaidInvoices,
            'total_unpaid_amount' => $totalUnpaid,
            'count' => $unpaidInvoices->count()
        ]);
    }

    /**
     * Get payment history for user's invoices.
     */
    public function paymentHistory(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = $user->payments()
            ->with(['invoice.pet'])
            ->orderBy('payment_date', 'desc');

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->has('payment_method')) {
            $query->byMethod($request->payment_method);
        }

        $perPage = $request->get('per_page', 15);
        $payments = $query->paginate($perPage);

        return ResponseHelper::success('Payment history retrieved successfully', [
            'payments' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ]
        ]);
    }

    /**
     * Download invoice as PDF (placeholder).
     */
    public function downloadPdf(Invoice $invoice): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this invoice
        if ($invoice->owner_id !== $user->id) {
            return ResponseHelper::error('You can only download your own invoices', 403);
        }

        // Here you would generate and return a PDF
        // For now, return invoice data that can be used to generate PDF on frontend
        $invoice->load([
            'pet',
            'doctor.user',
            'appointment',
            'items.service',
            'payments'
        ]);

        return ResponseHelper::success('Invoice data for PDF generation', [
            'invoice' => $invoice,
            'download_url' => route('user.invoices.pdf', $invoice->id) // You would implement this route
        ]);
    }

    /**
     * Print invoice (returns formatted data for printing).
     */
    public function print(Invoice $invoice): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this invoice
        if ($invoice->owner_id !== $user->id) {
            return ResponseHelper::error('You can only print your own invoices', 403);
        }

        $invoice->load([
            'pet',
            'doctor.user',
            'appointment',
            'items.service',
            'payments'
        ]);

        // Format data for printing
        $printData = [
            'invoice' => $invoice,
            'clinic_info' => [
                'name' => 'VetCare Clinic',
                'address' => '123 Pet Street, Animal City, AC 12345',
                'phone' => '(555) 123-4567',
                'email' => 'info@vetcare.com',
                'website' => 'www.vetcare.com',
            ],
            'formatted_date' => now()->format('F j, Y'),
        ];

        return ResponseHelper::success('Invoice formatted for printing', $printData);
    }
}
