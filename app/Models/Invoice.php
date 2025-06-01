<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'appointment_id',
        'pet_id',
        'owner_id',
        'doctor_id',
        'invoice_date',
        'due_date',
        'service_date',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'payment_status',
        'notes',
        'terms_and_conditions',
        'payment_instructions',
        'discount_type',
        'discount_value',
        'discount_reason',
        'sent_at',
        'viewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'service_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the appointment this invoice belongs to.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the pet this invoice is for.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the owner of the pet.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the doctor who provided the services.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get all items on this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get all payments for this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by payment status.
     */
    public function scopeByPaymentStatus($query, string $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /**
     * Scope to get overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereIn('payment_status', ['unpaid', 'partially_paid']);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('invoice_date', [$startDate, $endDate]);
    }

    /**
     * Calculate totals based on invoice items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum(function ($item) {
            return $item->total_price - $item->discount_amount;
        });

        // Apply invoice-level discount
        $discountAmount = 0;
        if ($this->discount_type === 'percentage' && $this->discount_value) {
            $discountAmount = ($this->subtotal * $this->discount_value) / 100;
        } elseif ($this->discount_type === 'fixed' && $this->discount_value) {
            $discountAmount = $this->discount_value;
        }
        $this->discount_amount = $discountAmount;

        // Calculate tax
        $taxableAmount = $this->subtotal - $this->discount_amount;
        $this->tax_amount = ($taxableAmount * $this->tax_rate) / 100;

        // Calculate total
        $this->total_amount = $taxableAmount + $this->tax_amount;

        // Calculate balance due
        $this->balance_due = $this->total_amount - $this->paid_amount;

        // Update payment status
        $this->updatePaymentStatus();
    }

    /**
     * Update payment status based on paid amount.
     */
    public function updatePaymentStatus(): void
    {
        if ($this->paid_amount <= 0) {
            $this->payment_status = 'unpaid';
        } elseif ($this->paid_amount >= $this->total_amount) {
            $this->payment_status = 'paid';
            if (!$this->paid_at) {
                $this->paid_at = now();
            }
        } else {
            $this->payment_status = 'partially_paid';
        }
    }

    /**
     * Add a payment to this invoice.
     */
    public function addPayment(float $amount, string $method, array $details = []): Payment
    {
        $payment = $this->payments()->create(array_merge([
            'payment_number' => $this->generatePaymentNumber(),
            'user_id' => $this->owner_id,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => now(),
            'status' => 'completed',
        ], $details));

        $this->paid_amount += $amount;
        $this->calculateTotals();
        $this->save();

        return $payment;
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date < now() && 
               in_array($this->payment_status, ['unpaid', 'partially_paid']);
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Mark invoice as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark invoice as viewed.
     */
    public function markAsViewed(): void
    {
        if (!$this->viewed_at) {
            $this->update([
                'status' => 'viewed',
                'viewed_at' => now(),
            ]);
        }
    }

    /**
     * Generate unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $month = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        $count = static::whereYear('created_at', $year)->count() + 1;
        $number = str_pad($count, 4, '0', STR_PAD_LEFT);
        
        return "INV-{$year}-{$month}-{$number}";
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

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return '$' . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted balance due.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return '$' . number_format($this->balance_due, 2);
    }
}
