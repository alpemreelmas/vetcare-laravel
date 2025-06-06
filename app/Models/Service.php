<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'base_price',
        'min_price',
        'max_price',
        'is_variable_pricing',
        'estimated_duration',
        'notes',
        'required_equipment',
        'is_active',
        'requires_appointment',
        'is_emergency_service',
        'service_code',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'is_variable_pricing' => 'boolean',
            'is_active' => 'boolean',
            'requires_appointment' => 'boolean',
            'is_emergency_service' => 'boolean',
            'required_equipment' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * Get all invoice items for this service.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Scope to get only active services.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter emergency services.
     */
    public function scopeEmergency($query)
    {
        return $query->where('is_emergency_service', true);
    }

    /**
     * Scope to search services by name or description.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('description', 'like', '%' . $search . '%')
              ->orWhere('service_code', 'like', '%' . $search . '%');
        });
    }

    /**
     * Get the effective price for this service.
     */
    public function getEffectivePrice(float $customPrice = null): float
    {
        if ($customPrice !== null && $this->is_variable_pricing) {
            // Validate custom price is within range
            if ($this->min_price && $customPrice < $this->min_price) {
                return $this->min_price;
            }
            if ($this->max_price && $customPrice > $this->max_price) {
                return $this->max_price;
            }
            return $customPrice;
        }

        return $this->base_price;
    }

    /**
     * Check if service is available for booking.
     */
    public function isAvailable(): bool
    {
        return $this->is_active;
    }

    /**
     * Get formatted price range.
     */
    public function getPriceRangeAttribute(): string
    {
        if ($this->is_variable_pricing && $this->min_price && $this->max_price) {
            return '$' . number_format($this->min_price, 2) . ' - $' . number_format($this->max_price, 2);
        }

        return '$' . number_format($this->base_price, 2);
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->estimated_duration) {
            return null;
        }

        $hours = intval($this->estimated_duration / 60);
        $minutes = $this->estimated_duration % 60;

        if ($hours > 0) {
            return $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
        }

        return $minutes . 'm';
    }

    /**
     * Generate unique service code.
     */
    public static function generateServiceCode(string $category, string $name): string
    {
        $categoryCode = strtoupper(substr($category, 0, 3));
        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        $number = str_pad(static::count() + 1, 3, '0', STR_PAD_LEFT);
        
        return $categoryCode . '-' . $nameCode . '-' . $number;
    }
}
