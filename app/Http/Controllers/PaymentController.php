<?php

namespace App\Http\Controllers;

use App\Core\Helpers\ResponseHelper;
use App\Models\Payment;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['invoice.pet.owner', 'user', 'processor']);

        // Apply filters
        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('payment_method')) {
            $query->byMethod($request->payment_method);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->dateRange($request->start_date, $request->end_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', '%' . $search . '%')
                  ->orWhere('transaction_id', 'like', '%' . $search . '%')
                  ->orWhere('reference_number', 'like', '%' . $search . '%')
                  ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                      $invoiceQuery->where('invoice_number', 'like', '%' . $search . '%');
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $payments = $query->orderBy('payment_date', 'desc')->paginate($perPage);

        return ResponseHelper::success('Payments retrieved successfully', [
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
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,credit_card,debit_card,bank_transfer,online_payment,check,mobile_payment,insurance,other',
            'transaction_id' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'card_last_four' => 'nullable|string|size:4',
            'card_type' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'check_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'fee_amount' => 'nullable|numeric|min:0',
        ]);

        $invoice = Invoice::findOrFail($validatedData['invoice_id']);

        // Validate payment amount doesn't exceed balance due
        if ($validatedData['amount'] > $invoice->balance_due) {
            return ResponseHelper::error('Payment amount cannot exceed balance due', 422);
        }

        DB::beginTransaction();
        try {
            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber();

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->owner_id,
                'processed_by' => auth()->id(),
                'payment_number' => $paymentNumber,
                'transaction_id' => $validatedData['transaction_id'] ?? null,
                'reference_number' => $validatedData['reference_number'] ?? null,
                'amount' => $validatedData['amount'],
                'payment_method' => $validatedData['payment_method'],
                'status' => 'completed',
                'payment_date' => now(),
                'processed_at' => now(),
                'card_last_four' => $validatedData['card_last_four'] ?? null,
                'card_type' => $validatedData['card_type'] ?? null,
                'bank_name' => $validatedData['bank_name'] ?? null,
                'check_number' => $validatedData['check_number'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'fee_amount' => $validatedData['fee_amount'] ?? 0,
            ]);

            // Update invoice
            $invoice->paid_amount += $validatedData['amount'];
            $invoice->calculateTotals();
            $invoice->save();

            $payment->load(['invoice.pet.owner', 'user', 'processor']);

            DB::commit();

            return ResponseHelper::success('Payment processed successfully', [
                'payment' => $payment,
                'invoice' => $invoice->fresh(['items'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to process payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['invoice.pet.owner', 'user', 'processor']);

        return ResponseHelper::success('Payment retrieved successfully', [
            'payment' => $payment
        ]);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, Payment $payment): JsonResponse
    {
        // Only allow updating certain fields
        $validatedData = $request->validate([
            'notes' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'status' => 'nullable|in:pending,processing,completed,failed,cancelled,refunded,disputed',
        ]);

        $payment->update($validatedData);

        $payment->load(['invoice.pet.owner', 'user', 'processor']);

        return ResponseHelper::success('Payment updated successfully', [
            'payment' => $payment
        ]);
    }

    /**
     * Process a refund for the specified payment.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        $refundAmount = $validatedData['amount'];
        $maxRefund = $payment->amount - $payment->refunded_amount;

        if ($refundAmount > $maxRefund) {
            return ResponseHelper::error('Refund amount cannot exceed available refund amount', 422);
        }

        DB::beginTransaction();
        try {
            $payment->processRefund($refundAmount, $validatedData['reason'] ?? null);

            $payment->load(['invoice.pet.owner', 'user', 'processor']);

            DB::commit();

            return ResponseHelper::success('Refund processed successfully', [
                'payment' => $payment,
                'refund_amount' => $refundAmount,
                'invoice' => $payment->invoice->fresh(['items'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to process refund: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get payment methods with statistics.
     */
    public function paymentMethods(): JsonResponse
    {
        $methods = Payment::selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total_amount')
            ->completed()
            ->groupBy('payment_method')
            ->orderBy('total_amount', 'desc')
            ->get();

        return ResponseHelper::success('Payment methods retrieved successfully', [
            'payment_methods' => $methods
        ]);
    }

    /**
     * Get payment statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_payments' => Payment::completed()->count(),
            'total_amount' => Payment::completed()->sum('amount'),
            'total_fees' => Payment::completed()->sum('fee_amount'),
            'total_refunds' => Payment::where('refunded_amount', '>', 0)->sum('refunded_amount'),
            'payments_by_method' => Payment::selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->completed()
                ->groupBy('payment_method')
                ->get(),
            'payments_by_status' => Payment::selectRaw('status, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('status')
                ->get(),
            'daily_payments' => Payment::selectRaw('DATE(payment_date) as date, COUNT(*) as count, SUM(amount) as total')
                ->completed()
                ->where('payment_date', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get(),
            'monthly_payments' => Payment::selectRaw('YEAR(payment_date) as year, MONTH(payment_date) as month, COUNT(*) as count, SUM(amount) as total')
                ->completed()
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get(),
        ];

        return ResponseHelper::success('Payment statistics retrieved successfully', $stats);
    }

    /**
     * Get payments for a specific invoice.
     */
    public function invoicePayments(Invoice $invoice): JsonResponse
    {
        $payments = $invoice->payments()
            ->with(['user', 'processor'])
            ->orderBy('payment_date', 'desc')
            ->get();

        return ResponseHelper::success('Invoice payments retrieved successfully', [
            'invoice' => $invoice->only(['id', 'invoice_number', 'total_amount', 'paid_amount', 'balance_due']),
            'payments' => $payments
        ]);
    }

    /**
     * Process online payment (placeholder for payment gateway integration).
     */
    public function processOnlinePayment(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_token' => 'required|string', // Token from payment gateway
            'card_last_four' => 'nullable|string|size:4',
            'card_type' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($validatedData['invoice_id']);

        // Validate payment amount
        if ($validatedData['amount'] > $invoice->balance_due) {
            return ResponseHelper::error('Payment amount cannot exceed balance due', 422);
        }

        DB::beginTransaction();
        try {
            // Here you would integrate with payment gateway
            // For now, we'll simulate a successful payment
            $gatewayResponse = [
                'transaction_id' => 'txn_' . uniqid(),
                'status' => 'success',
                'gateway' => 'stripe', // or whatever gateway you use
                'processed_at' => now()->toISOString(),
            ];

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->owner_id,
                'processed_by' => null, // Online payment
                'payment_number' => $this->generatePaymentNumber(),
                'transaction_id' => $gatewayResponse['transaction_id'],
                'amount' => $validatedData['amount'],
                'payment_method' => 'online_payment',
                'status' => 'completed',
                'payment_date' => now(),
                'processed_at' => now(),
                'card_last_four' => $validatedData['card_last_four'] ?? null,
                'card_type' => $validatedData['card_type'] ?? null,
                'gateway_response' => $gatewayResponse,
                'fee_amount' => $validatedData['amount'] * 0.029, // 2.9% processing fee
            ]);

            // Update invoice
            $invoice->paid_amount += $validatedData['amount'];
            $invoice->calculateTotals();
            $invoice->save();

            $payment->load(['invoice.pet.owner', 'user']);

            DB::commit();

            return ResponseHelper::success('Online payment processed successfully', [
                'payment' => $payment,
                'invoice' => $invoice->fresh(['items'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to process online payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate unique payment number.
     */
    private function generatePaymentNumber(): string
    {
        $year = now()->year;
        $month = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $count = Payment::whereYear('created_at', $year)->count() + 1;
        $number = str_pad($count, 4, '0', STR_PAD_LEFT);
        
        return "PAY-{$year}-{$month}-{$number}";
    }
}
