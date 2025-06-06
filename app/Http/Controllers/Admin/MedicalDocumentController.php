<?php

namespace App\Http\Controllers\Admin;

use App\Core\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\MedicalDocument;
use App\Models\MedicalRecord;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MedicalDocumentController extends Controller
{
    /**
     * Display a listing of all medical documents (Admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = MedicalDocument::with(['pet.owner', 'medicalRecord', 'uploader']);

        // Apply filters
        if ($request->has('pet_id')) {
            $query->where('pet_id', $request->pet_id);
        }

        if ($request->has('medical_record_id')) {
            $query->where('medical_record_id', $request->medical_record_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('visibility')) {
            $query->where('visibility', $request->visibility);
        }

        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->has('is_sensitive')) {
            $query->where('is_sensitive', $request->boolean('is_sensitive'));
        }

        if ($request->has('uploaded_by')) {
            $query->where('uploaded_by', $request->uploaded_by);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('document_date', [$request->start_date, $request->end_date]);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $perPage = $request->get('per_page', 15);
        $documents = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return ResponseHelper::success('Medical documents retrieved successfully', [
            'documents' => $documents->items(),
            'pagination' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ],
            'filters_applied' => $request->only(['pet_id', 'medical_record_id', 'type', 'visibility', 'is_archived', 'is_sensitive', 'uploaded_by', 'start_date', 'end_date', 'search'])
        ]);
    }

    /**
     * Store a newly created medical document (Admin can upload for any record).
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'medical_record_id' => 'required|exists:medical_records,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:xray,lab_report,blood_work,ultrasound,ct_scan,mri,prescription,vaccination_record,surgical_report,pathology_report,photo,other',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,gif,doc,docx,txt',
            'document_date' => 'nullable|date|before_or_equal:today',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_sensitive' => 'nullable|boolean',
            'visibility' => 'nullable|in:private,doctor_only,owner_and_doctor,public',
        ]);

        $medicalRecord = MedicalRecord::findOrFail($request->medical_record_id);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $fileHash = hash_file('md5', $file->getRealPath());
        
        // Check for duplicate files
        $existingDocument = MedicalDocument::where('file_hash', $fileHash)
            ->where('pet_id', $medicalRecord->pet_id)
            ->first();
            
        if ($existingDocument) {
            return ResponseHelper::error('This file has already been uploaded for this pet', 422);
        }

        // Generate unique file path
        $extension = $file->getClientOriginalExtension();
        $storedFileName = Str::uuid() . '.' . $extension;
        $filePath = 'medical-documents/' . $medicalRecord->pet_id . '/' . $storedFileName;

        // Store the file
        $file->storeAs('medical-documents/' . $medicalRecord->pet_id, $storedFileName, 'public');

        $document = MedicalDocument::create([
            'medical_record_id' => $medicalRecord->id,
            'pet_id' => $medicalRecord->pet_id,
            'uploaded_by' => auth()->id(),
            'title' => $validatedData['title'],
            'description' => $validatedData['description'] ?? null,
            'type' => $validatedData['type'],
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_hash' => $fileHash,
            'document_date' => $validatedData['document_date'] ?? now()->toDateString(),
            'tags' => $validatedData['tags'] ?? null,
            'is_sensitive' => $request->boolean('is_sensitive'),
            'visibility' => $validatedData['visibility'] ?? 'doctor_only',
        ]);

        $document->load(['pet.owner', 'medicalRecord', 'uploader']);

        return ResponseHelper::success('Medical document uploaded successfully', [
            'document' => $document
        ], 201);
    }

    /**
     * Display the specified medical document (Admin can view any document).
     */
    public function show(MedicalDocument $medicalDocument): JsonResponse
    {
        $medicalDocument->load(['pet.owner', 'medicalRecord', 'uploader']);

        return ResponseHelper::success('Medical document retrieved successfully', [
            'document' => $medicalDocument,
            'file_url' => $medicalDocument->getFileUrl(),
            'file_size_human' => $medicalDocument->getHumanReadableSize(),
        ]);
    }

    /**
     * Update the specified medical document (Admin can update any document).
     */
    public function update(Request $request, MedicalDocument $medicalDocument): JsonResponse
    {
        $validatedData = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:xray,lab_report,blood_work,ultrasound,ct_scan,mri,prescription,vaccination_record,surgical_report,pathology_report,photo,other',
            'document_date' => 'nullable|date|before_or_equal:today',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_sensitive' => 'nullable|boolean',
            'visibility' => 'nullable|in:private,doctor_only,owner_and_doctor,public',
        ]);

        $medicalDocument->update($validatedData);
        $medicalDocument->load(['pet.owner', 'medicalRecord', 'uploader']);

        return ResponseHelper::success('Medical document updated successfully', [
            'document' => $medicalDocument
        ]);
    }

    /**
     * Remove the specified medical document (Admin can delete any document).
     */
    public function destroy(MedicalDocument $medicalDocument): JsonResponse
    {
        $medicalDocument->delete(); // File will be deleted automatically via model boot method

        return ResponseHelper::success('Medical document deleted successfully');
    }

    /**
     * Download the specified medical document (Admin can download any document).
     */
    public function download(MedicalDocument $medicalDocument): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        if (!$medicalDocument->fileExists()) {
            return ResponseHelper::error('File not found on server', 404);
        }

        return Storage::disk('public')->download($medicalDocument->file_path, $medicalDocument->file_name);
    }

    /**
     * Archive/unarchive the specified medical document (Admin can archive any document).
     */
    public function toggleArchive(MedicalDocument $medicalDocument): JsonResponse
    {
        $isArchived = $medicalDocument->is_archived;
        $medicalDocument->update(['is_archived' => !$isArchived]);

        $action = $isArchived ? 'unarchived' : 'archived';

        return ResponseHelper::success("Medical document {$action} successfully", [
            'document' => $medicalDocument
        ]);
    }

    /**
     * Get documents for a specific pet (Admin can view any pet's documents).
     */
    public function petDocuments(Pet $pet): JsonResponse
    {
        $documents = $pet->medicalDocuments()
            ->with(['medicalRecord', 'uploader'])
            ->orderBy('document_date', 'desc')
            ->get();

        return ResponseHelper::success('Pet medical documents retrieved successfully', [
            'pet' => $pet->load('owner'),
            'documents' => $documents,
            'total_count' => $documents->count()
        ]);
    }

    /**
     * Get medical documents statistics (Admin only).
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_documents' => MedicalDocument::count(),
            'documents_by_type' => MedicalDocument::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get(),
            'documents_by_visibility' => MedicalDocument::selectRaw('visibility, COUNT(*) as count')
                ->groupBy('visibility')
                ->get(),
            'documents_this_month' => MedicalDocument::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'archived_documents' => MedicalDocument::where('is_archived', true)->count(),
            'sensitive_documents' => MedicalDocument::where('is_sensitive', true)->count(),
            'total_file_size' => MedicalDocument::sum('file_size'),
            'recent_uploads' => MedicalDocument::with(['pet', 'uploader'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ];

        // Convert file size to human readable
        $stats['total_file_size_human'] = $this->formatBytes($stats['total_file_size']);

        return ResponseHelper::success('Medical documents statistics retrieved successfully', $stats);
    }

    /**
     * Bulk update document visibility (Admin only).
     */
    public function bulkUpdateVisibility(Request $request): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'integer|exists:medical_documents,id',
            'visibility' => 'required|in:private,doctor_only,owner_and_doctor,public',
        ]);

        $updated = MedicalDocument::whereIn('id', $request->document_ids)
            ->update(['visibility' => $request->visibility]);

        return ResponseHelper::success("Updated {$updated} documents to {$request->visibility} visibility", [
            'updated_count' => $updated,
            'new_visibility' => $request->visibility
        ]);
    }

    /**
     * Bulk archive/unarchive documents (Admin only).
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $request->validate([
            'document_ids' => 'required|array',
            'document_ids.*' => 'integer|exists:medical_documents,id',
            'archive' => 'required|boolean',
        ]);

        $updated = MedicalDocument::whereIn('id', $request->document_ids)
            ->update(['is_archived' => $request->archive]);

        $action = $request->archive ? 'archived' : 'unarchived';

        return ResponseHelper::success("Successfully {$action} {$updated} documents", [
            'updated_count' => $updated,
            'action' => $action
        ]);
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
