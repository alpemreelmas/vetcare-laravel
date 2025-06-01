<?php

namespace App\Models;

use App\Services\TreatmentBillingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Treatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_record_id',
        'pet_id',
        'diagnosis_id',
        'type',
        'name',
        'description',
        'medication_name',
        'dosage',
        'frequency',
        'route',
        'duration_days',
        'procedure_code',
        'procedure_notes',
        'anesthesia_type',
        'start_date',
        'end_date',
        'administered_at',
        'status',
        'instructions',
        'side_effects',
        'response_notes',
        'cost',
        'billing_code',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'administered_at' => 'datetime',
            'cost' => 'decimal:2',
            'duration_days' => 'integer',
        ];
    }

    /**
     * Boot method to handle model events for automatic billing.
     */
    protected static function boot()
    {
        parent::boot();

        // Create invoice when treatment with cost is created
        static::created(function ($treatment) {
            if ($treatment->cost && $treatment->cost > 0) {
                $billingService = app(TreatmentBillingService::class);
                $billingService->createInvoiceForTreatment($treatment);
            }
        });

        // Update invoice when treatment cost changes
        static::updated(function ($treatment) {
            if ($treatment->wasChanged('cost') || $treatment->wasChanged('name') || $treatment->wasChanged('description')) {
                $billingService = app(TreatmentBillingService::class);
                $billingService->updateInvoiceForTreatment($treatment);
            }
        });

        // Remove invoice when treatment is deleted
        static::deleting(function ($treatment) {
            $billingService = app(TreatmentBillingService::class);
            $billingService->removeInvoiceForTreatment($treatment);
        });
    }

    /**
     * Get the medical record this treatment belongs to.
     */
    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    /**
     * Get the pet this treatment belongs to.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the diagnosis this treatment is for.
     */
    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(Diagnosis::class);
    }

    /**
     * Get the invoice item for this treatment (if any).
     */
    public function invoiceItem(): ?object
    {
        return \App\Models\InvoiceItem::where('metadata->treatment_id', $this->id)->first();
    }

    /**
     * Get the invoice for this treatment (if any).
     */
    public function invoice(): ?object
    {
        $invoiceItem = $this->invoiceItem();
        return $invoiceItem ? $invoiceItem->invoice : null;
    }

    /**
     * Check if this treatment has been billed.
     */
    public function isBilled(): bool
    {
        return $this->invoiceItem() !== null;
    }

    /**
     * Check if this treatment is payable (has cost).
     */
    public function isPayable(): bool
    {
        return $this->cost && $this->cost > 0;
    }

    /**
     * Scope to filter by pet.
     */
    public function scopeForPet($query, int $petId)
    {
        return $query->where('pet_id', $petId);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get medications only.
     */
    public function scopeMedications($query)
    {
        return $query->where('type', 'medication');
    }

    /**
     * Scope to get procedures only.
     */
    public function scopeProcedures($query)
    {
        return $query->whereIn('type', ['procedure', 'surgery']);
    }

    /**
     * Scope to get active treatments.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['prescribed', 'in_progress']);
    }

    /**
     * Scope to get completed treatments.
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
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get current treatments (not ended).
     */
    public function scopeCurrent($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now()->toDateString());
        })->whereIn('status', ['prescribed', 'in_progress']);
    }

    /**
     * Scope to get payable treatments (with cost).
     */
    public function scopePayable($query)
    {
        return $query->where('cost', '>', 0);
    }

    /**
     * Scope to get billed treatments.
     */
    public function scopeBilled($query)
    {
        return $query->whereExists(function ($subQuery) {
            $subQuery->select(DB::raw(1))
                ->from('invoice_items')
                ->whereRaw('JSON_EXTRACT(metadata, "$.treatment_id") = treatments.id');
        });
    }

    /**
     * Check if this treatment is a medication.
     */
    public function isMedication(): bool
    {
        return $this->type === 'medication';
    }

    /**
     * Check if this treatment is a procedure.
     */
    public function isProcedure(): bool
    {
        return in_array($this->type, ['procedure', 'surgery']);
    }

    /**
     * Check if this treatment is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['prescribed', 'in_progress']);
    }

    /**
     * Check if this treatment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark this treatment as completed.
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => 'completed',
            'end_date' => $this->end_date ?? now()->toDateString(),
        ]);
    }

    /**
     * Mark this treatment as administered.
     */
    public function markAsAdministered(): bool
    {
        return $this->update([
            'administered_at' => now(),
            'status' => $this->isMedication() ? 'in_progress' : 'completed',
        ]);
    }

    /**
     * Get the remaining days for this treatment.
     */
    public function getRemainingDays(): ?int
    {
        if (!$this->end_date) {
            return null;
        }

        $today = now()->toDateString();
        if ($this->end_date < $today) {
            return 0;
        }

        return now()->diffInDays($this->end_date);
    }

    /**
     * Get the total duration of this treatment in days.
     */
    public function getTotalDurationDays(): ?int
    {
        if (!$this->start_date) {
            return $this->duration_days;
        }

        $endDate = $this->end_date ?? now();
        return $this->start_date->diffInDays($endDate) + 1;
    }
}
