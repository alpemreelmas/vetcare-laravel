<?php

namespace App\Http\Controllers\Doctor;

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
     * Display medical documents for the authenticated doctor.
     */
    public function index(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $query = MedicalDocument::with(['pet.owner', 'medicalRecord', 'uploader'])
            ->where(function ($q) use ($doctor) {
                $q->where('uploaded_by', auth()->id())
                  ->orWhereHas('pet.medicalRecords', function ($medicalQuery) use ($doctor) {
                      $medicalQuery->where('doctor_id', $doctor->id);
                  });
            });

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

        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->has('my_uploads_only')) {
            if ($request->boolean('my_uploads_only')) {
                $query->where('uploaded_by', auth()->id());
            }
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
            ]
        ]);
    }

    /**
     * Store a newly created medical document for doctor's medical record.
     */
    public function store(Request $request): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

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

        // Check if this medical record belongs to the doctor
        if ($medicalRecord->doctor_id !== $doctor->id) {
            return ResponseHelper::error('You can only upload documents for your own medical records', 403);
        }

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

        $document->load(['pet.owner', 'medicalRecord']);

        return ResponseHelper::success('Medical document uploaded successfully', [
            'document' => $document
        ], 201);
    }

    /**
     * Display the specified medical document (only if doctor has access).
     */
    public function show(MedicalDocument $medicalDocument): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if doctor has access to this document
        $hasAccess = $medicalDocument->uploaded_by === auth()->id() ||
                    $medicalDocument->medicalRecord->doctor_id === $doctor->id;

        if (!$hasAccess) {
            return ResponseHelper::error('Document not found', 404);
        }

        $medicalDocument->load(['pet.owner', 'medicalRecord', 'uploader']);

        return ResponseHelper::success('Medical document retrieved successfully', [
            'document' => $medicalDocument,
            'file_url' => $medicalDocument->getFileUrl(),
            'file_size_human' => $medicalDocument->getHumanReadableSize(),
        ]);
    }

    /**
     * Update the specified medical document (only doctor's own uploads).
     */
    public function update(Request $request, MedicalDocument $medicalDocument): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this document was uploaded by the doctor or belongs to their medical record
        $canUpdate = $medicalDocument->uploaded_by === auth()->id() ||
                    $medicalDocument->medicalRecord->doctor_id === $doctor->id;

        if (!$canUpdate) {
            return ResponseHelper::error('You can only update your own documents', 403);
        }

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
     * Remove the specified medical document (only doctor's own uploads).
     */
    public function destroy(MedicalDocument $medicalDocument): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this document was uploaded by the doctor or belongs to their medical record
        $canDelete = $medicalDocument->uploaded_by === auth()->id() ||
                    $medicalDocument->medicalRecord->doctor_id === $doctor->id;

        if (!$canDelete) {
            return ResponseHelper::error('You can only delete your own documents', 403);
        }

        $medicalDocument->delete(); // File will be deleted automatically via model boot method

        return ResponseHelper::success('Medical document deleted successfully');
    }

    /**
     * Download the specified medical document (if doctor has access).
     */
    public function download(MedicalDocument $medicalDocument): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if doctor has access to this document
        $hasAccess = $medicalDocument->uploaded_by === auth()->id() ||
                    $medicalDocument->medicalRecord->doctor_id === $doctor->id;

        if (!$hasAccess) {
            return ResponseHelper::error('Document not found', 404);
        }

        if (!$medicalDocument->fileExists()) {
            return ResponseHelper::error('File not found on server', 404);
        }

        return Storage::disk('public')->download($medicalDocument->file_path, $medicalDocument->file_name);
    }

    /**
     * Archive/unarchive the specified medical document (doctor's own documents).
     */
    public function toggleArchive(MedicalDocument $medicalDocument): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if this document was uploaded by the doctor or belongs to their medical record
        $canArchive = $medicalDocument->uploaded_by === auth()->id() ||
                     $medicalDocument->medicalRecord->doctor_id === $doctor->id;

        if (!$canArchive) {
            return ResponseHelper::error('You can only archive your own documents', 403);
        }

        $isArchived = $medicalDocument->is_archived;
        $medicalDocument->update(['is_archived' => !$isArchived]);

        $action = $isArchived ? 'unarchived' : 'archived';

        return ResponseHelper::success("Medical document {$action} successfully", [
            'document' => $medicalDocument
        ]);
    }

    /**
     * Get documents for a pet (only if doctor has treated the pet).
     */
    public function petDocuments(Pet $pet): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        // Check if doctor has treated this pet
        $hasAccess = MedicalRecord::where('pet_id', $pet->id)
            ->where('doctor_id', $doctor->id)
            ->exists();

        if (!$hasAccess) {
            return ResponseHelper::error('You can only view documents for pets you have treated', 403);
        }

        $documents = $pet->medicalDocuments()
            ->with(['medicalRecord', 'uploader'])
            ->whereIn('visibility', ['doctor_only', 'owner_and_doctor', 'public'])
            ->orderBy('document_date', 'desc')
            ->get();

        return ResponseHelper::success('Pet medical documents retrieved successfully', [
            'pet' => $pet->load('owner'),
            'documents' => $documents,
            'total_count' => $documents->count()
        ]);
    }

    /**
     * Get doctor's document upload statistics.
     */
    public function myStatistics(): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $stats = [
            'total_uploads' => MedicalDocument::where('uploaded_by', auth()->id())->count(),
            'uploads_by_type' => MedicalDocument::where('uploaded_by', auth()->id())
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get(),
            'uploads_this_month' => MedicalDocument::where('uploaded_by', auth()->id())
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'total_file_size' => MedicalDocument::where('uploaded_by', auth()->id())->sum('file_size'),
            'recent_uploads' => MedicalDocument::where('uploaded_by', auth()->id())
                ->with(['pet'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        // Convert file size to human readable
        $stats['total_file_size_human'] = $this->formatBytes($stats['total_file_size']);

        return ResponseHelper::success('Your document statistics retrieved successfully', $stats);
    }

    /**
     * Get doctor's recent uploads.
     */
    public function myRecentUploads(): JsonResponse
    {
        $doctor = auth()->user()->doctor;
        
        if (!$doctor) {
            return ResponseHelper::error('Doctor profile not found', 404);
        }

        $recentUploads = MedicalDocument::where('uploaded_by', auth()->id())
            ->with(['pet.owner', 'medicalRecord'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return ResponseHelper::success('Your recent uploads retrieved successfully', [
            'recent_uploads' => $recentUploads,
            'total_count' => $recentUploads->count()
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
