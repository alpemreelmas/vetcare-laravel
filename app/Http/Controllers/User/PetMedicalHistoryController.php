<?php

namespace App\Http\Controllers\User;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\MedicalRecord;
use App\Models\MedicalDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetMedicalHistoryController extends Controller
{
    /**
     * Get medical history for user's pet.
     */
    public function petHistory(Pet $pet): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        $medicalRecords = $pet->medicalRecords()
            ->with(['doctor.user', 'appointment', 'diagnoses', 'treatments'])
            ->orderBy('visit_date', 'desc')
            ->get();

        $summary = $pet->getMedicalHistorySummary();

        return ResponseHelper::success('Pet medical history retrieved successfully', [
            'pet' => $pet,
            'medical_records' => $medicalRecords,
            'summary' => $summary
        ]);
    }

    /**
     * Get specific medical record for user's pet.
     */
    public function getMedicalRecord(Pet $pet, MedicalRecord $medicalRecord): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        // Check if medical record belongs to this pet
        if ($medicalRecord->pet_id !== $pet->id) {
            return ResponseHelper::error('Medical record not found', 404);
        }

        $medicalRecord->load([
            'doctor.user',
            'appointment',
            'diagnoses',
            'treatments',
            'documents' => function ($query) {
                // Only show documents visible to pet owners
                $query->whereIn('visibility', ['owner_and_doctor', 'public']);
            }
        ]);

        return ResponseHelper::success('Medical record retrieved successfully', [
            'medical_record' => $medicalRecord
        ]);
    }

    /**
     * Get medical documents for user's pet.
     */
    public function petDocuments(Pet $pet, Request $request): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        $query = $pet->medicalDocuments()
            ->with(['medicalRecord', 'uploader'])
            ->whereIn('visibility', ['owner_and_doctor', 'public'])
            ->where('is_archived', false);

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('document_date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $documents = $query->orderBy('document_date', 'desc')->get();

        return ResponseHelper::success('Pet medical documents retrieved successfully', [
            'pet' => $pet,
            'documents' => $documents,
            'total_count' => $documents->count()
        ]);
    }

    /**
     * Get specific medical document for user's pet.
     */
    public function getDocument(Pet $pet, MedicalDocument $document): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        // Check if document belongs to this pet
        if ($document->pet_id !== $pet->id) {
            return ResponseHelper::error('Document not found', 404);
        }

        // Check if user can view this document
        if (!in_array($document->visibility, ['owner_and_doctor', 'public'])) {
            return ResponseHelper::error('Document not found', 404);
        }

        $document->load(['medicalRecord', 'uploader']);

        return ResponseHelper::success('Medical document retrieved successfully', [
            'document' => $document,
            'file_url' => $document->getFileUrl(),
            'file_size_human' => $document->getHumanReadableSize(),
        ]);
    }

    /**
     * Download medical document for user's pet.
     */
    public function downloadDocument(Pet $pet, MedicalDocument $document): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        // Check if document belongs to this pet
        if ($document->pet_id !== $pet->id) {
            return ResponseHelper::error('Document not found', 404);
        }

        // Check if user can view this document
        if (!in_array($document->visibility, ['owner_and_doctor', 'public'])) {
            return ResponseHelper::error('Document not found', 404);
        }

        if (!$document->fileExists()) {
            return ResponseHelper::error('File not found on server', 404);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    /**
     * Get user's pets with medical summary.
     */
    public function myPetsWithMedicalSummary(): JsonResponse
    {
        $user = auth()->user();

        $pets = Pet::where('owner_id', $user->id)
            ->with(['medicalRecords' => function ($query) {
                $query->latest('visit_date')->limit(1);
            }])
            ->get()
            ->map(function ($pet) {
                return [
                    'id' => $pet->id,
                    'name' => $pet->name,
                    'species' => $pet->species,
                    'breed' => $pet->breed,
                    'age' => $pet->age,
                    'medical_summary' => $pet->getMedicalHistorySummary(),
                    'last_visit' => $pet->medicalRecords->first()?->visit_date,
                ];
            });

        return ResponseHelper::success('Your pets with medical summary retrieved successfully', [
            'pets' => $pets,
            'total_pets' => $pets->count()
        ]);
    }

    /**
     * Get active diagnoses for user's pet.
     */
    public function petActiveDiagnoses(Pet $pet): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        $activeDiagnoses = $pet->getActiveDiagnoses();
        $chronicConditions = $pet->getChronicConditions();

        return ResponseHelper::success('Pet active diagnoses retrieved successfully', [
            'pet' => $pet,
            'active_diagnoses' => $activeDiagnoses,
            'chronic_conditions' => $chronicConditions,
            'total_active' => $activeDiagnoses->count(),
            'total_chronic' => $chronicConditions->count()
        ]);
    }

    /**
     * Get current treatments for user's pet.
     */
    public function petCurrentTreatments(Pet $pet): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        $currentTreatments = $pet->getCurrentTreatments();

        return ResponseHelper::success('Pet current treatments retrieved successfully', [
            'pet' => $pet,
            'current_treatments' => $currentTreatments,
            'total_treatments' => $currentTreatments->count()
        ]);
    }

    /**
     * Get upcoming appointments for user's pet.
     */
    public function petUpcomingAppointments(Pet $pet): JsonResponse
    {
        $user = auth()->user();

        // Check if user owns this pet
        if ($pet->owner_id !== $user->id) {
            return ResponseHelper::error('Pet not found', 404);
        }

        $upcomingAppointments = $pet->appointments()
            ->with(['doctor.user'])
            ->where('start_datetime', '>', now())
            ->orderBy('start_datetime')
            ->get();

        return ResponseHelper::success('Pet upcoming appointments retrieved successfully', [
            'pet' => $pet,
            'upcoming_appointments' => $upcomingAppointments,
            'total_upcoming' => $upcomingAppointments->count()
        ]);
    }
}
