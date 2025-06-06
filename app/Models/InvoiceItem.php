<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'service_id',
        'service_name',
        'description',
        'service_code',
        'quantity',
        'unit_price',
        'total_price',
        'discount_amount',
        'discount_reason',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the invoice this item belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the service this item is based on.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Calculate total price based on quantity and unit price.
     */
    public function calculateTotalPrice(): void
    {
        $this->total_price = $this->quantity * $this->unit_price;
    }

    /**
     * Apply discount to this item.
     */
    public function applyDiscount(float $amount, string $reason = null): void
    {
        $this->discount_amount = $amount;
        $this->discount_reason = $reason;
    }

    /**
     * Get the net amount (total price minus discount).
     */
    public function getNetAmountAttribute(): float
    {
        return $this->total_price - $this->discount_amount;
    }

    /**
     * Get formatted unit price.
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return '$' . number_format($this->unit_price, 2);
    }

    /**
     * Get formatted total price.
     */
    public function getFormattedTotalPriceAttribute(): string
    {
        return '$' . number_format($this->total_price, 2);
    }

    /**
     * Get formatted net amount.
     */
    public function getFormattedNetAmountAttribute(): string
    {
        return '$' . number_format($this->net_amount, 2);
    }

    /**
     * Boot method to handle model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate total price when creating/updating
        static::saving(function ($item) {
            $item->calculateTotalPrice();
        });

        // Update invoice totals when item is saved or deleted
        static::saved(function ($item) {
            $item->invoice->calculateTotals();
            $item->invoice->save();
        });

        static::deleted(function ($item) {
            $item->invoice->calculateTotals();
            $item->invoice->save();
        });
    }
}
