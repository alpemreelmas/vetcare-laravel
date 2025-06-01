<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'processed_by',
        'payment_number',
        'transaction_id',
        'reference_number',
        'amount',
        'payment_method',
        'status',
        'payment_date',
        'processed_at',
        'cleared_at',
        'card_last_four',
        'card_type',
        'bank_name',
        'check_number',
        'gateway_response',
        'notes',
        'failure_reason',
        'fee_amount',
        'currency',
        'refunded_amount',
        'refunded_at',
        'refund_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'payment_date' => 'datetime',
            'processed_at' => 'datetime',
            'cleared_at' => 'datetime',
            'refunded_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    /**
     * Get the invoice this payment belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who made the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the staff member who processed the payment.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope to filter by payment method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Check if payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if payment is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->refunded_amount > 0;
    }

    /**
     * Mark payment as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    /**
     * Mark payment as failed.
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
    }

    /**
     * Process a refund for this payment.
     */
    public function processRefund(float $amount, string $reason = null): void
    {
        $refundAmount = min($amount, $this->amount - $this->refunded_amount);
        
        $this->update([
            'refunded_amount' => $this->refunded_amount + $refundAmount,
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);

        // Update invoice paid amount
        $this->invoice->paid_amount -= $refundAmount;
        $this->invoice->calculateTotals();
        $this->invoice->save();
    }

    /**
     * Get the net payment amount (amount minus fees and refunds).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->amount - $this->fee_amount - $this->refunded_amount;
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get formatted net amount.
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return '$' . number_format($this->net_amount, 2);
    }

    /**
     * Get masked card number for display.
     */
    public function getMaskedCardAttribute(): ?string
    {
        if (!$this->card_last_four) {
            return null;
        }

        return '**** **** **** ' . $this->card_last_four;
    }

    /**
     * Get payment method display name.
     */
    public function getPaymentMethodDisplayAttribute(): string
    {
        return match($this->payment_method) {
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'online_payment' => 'Online Payment',
            'mobile_payment' => 'Mobile Payment',
            default => ucfirst(str_replace('_', ' ', $this->payment_method))
        };
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Update invoice totals when payment is saved
        static::saved(function ($payment) {
            if ($payment->isCompleted() && $payment->wasChanged('status')) {
                $invoice = $payment->invoice;
                $invoice->paid_amount = $invoice->payments()->completed()->sum('amount');
                $invoice->calculateTotals();
                $invoice->save();
            }
        });
    }
}
