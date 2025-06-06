<?php

namespace App\Services;

use App\Models\Treatment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TreatmentBillingService
{
    /**
     * Automatically create invoice for payable treatment.
     */
    public function createInvoiceForTreatment(Treatment $treatment): ?Invoice
    {
        // Only create invoice if treatment has a cost
        if (!$treatment->cost || $treatment->cost <= 0) {
            return null;
        }

        // Check if invoice already exists for this treatment
        if ($this->treatmentHasInvoice($treatment)) {
            return null;
        }

        DB::beginTransaction();
        try {
            // Get or create service for this treatment
            $service = $this->getOrCreateServiceForTreatment($treatment);

            // Create invoice
            $invoice = $this->createInvoice($treatment, $service);

            DB::commit();

            Log::info('Automatic invoice created for treatment', [
                'treatment_id' => $treatment->id,
                'invoice_id' => $invoice->id,
                'amount' => $treatment->cost
            ]);

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create automatic invoice for treatment', [
                'treatment_id' => $treatment->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if treatment already has an invoice.
     */
    private function treatmentHasInvoice(Treatment $treatment): bool
    {
        return InvoiceItem::where('metadata->treatment_id', $treatment->id)->exists();
    }

    /**
     * Get or create service for treatment.
     */
    private function getOrCreateServiceForTreatment(Treatment $treatment): Service
    {
        // Try to find existing service by billing code
        if ($treatment->billing_code) {
            $service = Service::where('service_code', $treatment->billing_code)->first();
            if ($service) {
                return $service;
            }
        }

        // Try to find service by treatment type and name
        $serviceName = $this->generateServiceName($treatment);
        $service = Service::where('name', $serviceName)
            ->where('category', $this->mapTreatmentTypeToServiceCategory($treatment->type))
            ->first();

        if ($service) {
            return $service;
        }

        // Create new service for this treatment
        return $this->createServiceForTreatment($treatment);
    }

    /**
     * Create a new service for the treatment.
     */
    private function createServiceForTreatment(Treatment $treatment): Service
    {
        $serviceName = $this->generateServiceName($treatment);
        $category = $this->mapTreatmentTypeToServiceCategory($treatment->type);

        return Service::create([
            'name' => $serviceName,
            'description' => $treatment->description ?: "Auto-generated service for {$treatment->type}",
            'category' => $category,
            'base_price' => $treatment->cost,
            'service_code' => $treatment->billing_code ?: Service::generateServiceCode($category, $serviceName),
            'is_active' => true,
            'requires_appointment' => false,
            'tags' => ['auto-generated', 'treatment-based'],
        ]);
    }

    /**
     * Generate service name from treatment.
     */
    private function generateServiceName(Treatment $treatment): string
    {
        if ($treatment->name) {
            return $treatment->name;
        }

        if ($treatment->medication_name) {
            return "Medication: {$treatment->medication_name}";
        }

        if ($treatment->procedure_code) {
            return "Procedure: {$treatment->procedure_code}";
        }

        return ucfirst($treatment->type) . " Treatment";
    }

    /**
     * Map treatment type to service category.
     */
    private function mapTreatmentTypeToServiceCategory(string $treatmentType): string
    {
        return match($treatmentType) {
            'medication' => 'treatment',
            'procedure' => 'treatment',
            'surgery' => 'surgery',
            'therapy' => 'treatment',
            'vaccination' => 'vaccination',
            'diagnostic_test' => 'diagnostic',
            default => 'treatment'
        };
    }

    /**
     * Create invoice for treatment.
     */
    private function createInvoice(Treatment $treatment, Service $service): Invoice
    {
        $medicalRecord = $treatment->medicalRecord;
        $pet = $treatment->pet;
        $owner = $pet->owner;

        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'appointment_id' => $medicalRecord->appointment_id,
            'pet_id' => $pet->id,
            'owner_id' => $owner->id,
            'doctor_id' => $medicalRecord->doctor_id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'service_date' => $treatment->start_date->toDateString(),
            'tax_rate' => 0, // Can be configured
            'subtotal' => 0,
            'total_amount' => 0,
            'balance_due' => 0,
            'notes' => "Auto-generated invoice for treatment: {$treatment->name}",
        ]);

        // Create invoice item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'description' => $treatment->description ?: $service->description,
            'service_code' => $service->service_code,
            'quantity' => 1,
            'unit_price' => $treatment->cost,
            'total_price' => $treatment->cost,
            'notes' => "Treatment: {$treatment->name}",
            'metadata' => [
                'treatment_id' => $treatment->id,
                'treatment_type' => $treatment->type,
                'auto_generated' => true,
            ],
        ]);

        // Calculate totals
        $invoice->calculateTotals();
        $invoice->save();

        return $invoice;
    }

    /**
     * Update invoice when treatment cost changes.
     */
    public function updateInvoiceForTreatment(Treatment $treatment): ?Invoice
    {
        $invoiceItem = InvoiceItem::where('metadata->treatment_id', $treatment->id)->first();
        
        if (!$invoiceItem) {
            // No existing invoice, create new one if treatment has cost
            if ($treatment->cost && $treatment->cost > 0) {
                return $this->createInvoiceForTreatment($treatment);
            }
            return null;
        }

        $invoice = $invoiceItem->invoice;

        // Don't update if invoice has payments
        if ($invoice->payments()->exists()) {
            return $invoice;
        }

        DB::beginTransaction();
        try {
            if ($treatment->cost && $treatment->cost > 0) {
                // Update existing invoice item
                $invoiceItem->update([
                    'unit_price' => $treatment->cost,
                    'total_price' => $treatment->cost,
                ]);
            } else {
                // Remove invoice item if no cost
                $invoiceItem->delete();
            }

            // Recalculate invoice totals
            $invoice->calculateTotals();
            $invoice->save();

            // Delete invoice if no items remain
            if ($invoice->items()->count() === 0) {
                $invoice->delete();
                DB::commit();
                return null;
            }

            DB::commit();
            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update invoice for treatment', [
                'treatment_id' => $treatment->id,
                'error' => $e->getMessage()
            ]);
            return $invoice;
        }
    }

    /**
     * Remove invoice when treatment is deleted.
     */
    public function removeInvoiceForTreatment(Treatment $treatment): void
    {
        $invoiceItem = InvoiceItem::where('metadata->treatment_id', $treatment->id)->first();
        
        if (!$invoiceItem) {
            return;
        }

        $invoice = $invoiceItem->invoice;

        // Don't remove if invoice has payments
        if ($invoice->payments()->exists()) {
            return;
        }

        DB::beginTransaction();
        try {
            $invoiceItem->delete();

            // Delete invoice if no items remain
            if ($invoice->items()->count() === 0) {
                $invoice->delete();
            } else {
                // Recalculate totals
                $invoice->calculateTotals();
                $invoice->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to remove invoice for treatment', [
                'treatment_id' => $treatment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 