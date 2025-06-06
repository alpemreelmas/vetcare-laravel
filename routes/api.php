<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\PaymentController;

Route::prefix("/auth")->group(
    function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(
            function () {
                Route::post('/logout', [AuthController::class, 'logout']);

                Route::get(
                    '/profile',
                    function (Request $request) {
                        return $request->user();
                    }
                );
            }
        );
    }
);

Route::prefix('users')
    ->middleware("auth:sanctum")
    ->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('{userId}', [UserController::class, 'show']);
        Route::put('{userId}', [UserController::class, 'update']);
    });

// Admin-only routes
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin doctor management
    Route::apiResource('doctors', \App\Http\Controllers\DoctorController::class);
    
    // Admin pet management
    Route::prefix('admin/pets')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PetController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\PetController::class, 'store']);
        Route::get('statistics', [\App\Http\Controllers\Admin\PetController::class, 'statistics']);
        Route::get('owner/{owner}', [\App\Http\Controllers\Admin\PetController::class, 'getByOwner']);
        Route::get('{pet}', [\App\Http\Controllers\Admin\PetController::class, 'show']);
        Route::put('{pet}', [\App\Http\Controllers\Admin\PetController::class, 'update']);
        Route::delete('{pet}', [\App\Http\Controllers\Admin\PetController::class, 'destroy']);
    });

    // Admin medical records management
    Route::prefix('admin/medical-records')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'store']);
        Route::get('statistics', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'statistics']);
        Route::get('doctor/{doctor}', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'byDoctor']);
        Route::get('pet/{pet}', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'byPet']);
        Route::patch('bulk-status', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'bulkUpdateStatus']);
        Route::get('{medicalRecord}', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'show']);
        Route::put('{medicalRecord}', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'update']);
        Route::delete('{medicalRecord}', [\App\Http\Controllers\Admin\MedicalRecordController::class, 'destroy']);
    });

    // Admin medical documents management
    Route::prefix('admin/medical-documents')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'store']);
        Route::get('statistics', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'statistics']);
        Route::patch('bulk-visibility', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'bulkUpdateVisibility']);
        Route::patch('bulk-archive', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'bulkArchive']);
        Route::get('pet/{pet}', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'petDocuments']);
        Route::get('{medicalDocument}', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'show']);
        Route::put('{medicalDocument}', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'update']);
        Route::delete('{medicalDocument}', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'destroy']);
        Route::get('{medicalDocument}/download', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'download']);
        Route::patch('{medicalDocument}/toggle-archive', [\App\Http\Controllers\Admin\MedicalDocumentController::class, 'toggleArchive']);
    });

    // Admin treatment management
    Route::prefix('admin/treatments')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TreatmentController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\TreatmentController::class, 'store']);
        Route::get('statistics', [\App\Http\Controllers\Admin\TreatmentController::class, 'statistics']);
        Route::get('doctor/{doctor}', [\App\Http\Controllers\Admin\TreatmentController::class, 'byDoctor']);
        Route::get('pet/{pet}', [\App\Http\Controllers\Admin\TreatmentController::class, 'byPet']);
        Route::patch('bulk-status', [\App\Http\Controllers\Admin\TreatmentController::class, 'bulkUpdateStatus']);
        Route::get('{treatment}', [\App\Http\Controllers\Admin\TreatmentController::class, 'show']);
        Route::put('{treatment}', [\App\Http\Controllers\Admin\TreatmentController::class, 'update']);
        Route::delete('{treatment}', [\App\Http\Controllers\Admin\TreatmentController::class, 'destroy']);
    });

    // Service Management
    Route::apiResource('services', ServiceController::class)->except(['index', 'show']);
    
    // Invoice Management
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InvoiceController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Admin\InvoiceController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Admin\InvoiceController::class, 'statistics']);
        Route::get('/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show']);
        Route::put('/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'update']);
        Route::delete('/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'destroy']);
        Route::post('/{invoice}/send', [\App\Http\Controllers\Admin\InvoiceController::class, 'sendInvoice']);
        Route::post('/{invoice}/mark-viewed', [\App\Http\Controllers\Admin\InvoiceController::class, 'markAsViewed']);
    });
    
    // Payment Management
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/statistics', [PaymentController::class, 'statistics']);
        Route::get('/methods', [PaymentController::class, 'paymentMethods']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
        Route::put('/{payment}', [PaymentController::class, 'update']);
        Route::post('/{payment}/refund', [PaymentController::class, 'refund']);
    });
});

// Doctor-only routes
Route::middleware(['auth:sanctum', 'role:doctor'])->group(function () {
    // Doctor medical records management
    Route::prefix('doctor/medical-records')->group(function () {
        Route::get('/', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'store']);
        Route::get('pending', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'pendingRecords']);
        Route::get('statistics', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'myStatistics']);
        Route::get('pet/{pet}/history', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'petHistory']);
        Route::post('appointment/{appointment}/create', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'createFromAppointment']);
        Route::get('{medicalRecord}', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'show']);
        Route::put('{medicalRecord}', [\App\Http\Controllers\Doctor\MedicalRecordController::class, 'update']);
    });

    // Doctor medical documents management
    Route::prefix('doctor/medical-documents')->group(function () {
        Route::get('/', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'store']);
        Route::get('statistics', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'myStatistics']);
        Route::get('recent-uploads', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'myRecentUploads']);
        Route::get('pet/{pet}', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'petDocuments']);
        Route::get('{medicalDocument}', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'show']);
        Route::put('{medicalDocument}', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'update']);
        Route::delete('{medicalDocument}', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'destroy']);
        Route::get('{medicalDocument}/download', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'download']);
        Route::patch('{medicalDocument}/toggle-archive', [\App\Http\Controllers\Doctor\MedicalDocumentController::class, 'toggleArchive']);
    });

    // Doctor treatment management
    Route::prefix('doctor/treatments')->group(function () {
        Route::get('/', [\App\Http\Controllers\Doctor\TreatmentController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Doctor\TreatmentController::class, 'store']);
        Route::get('medical-record/{medicalRecord}', [\App\Http\Controllers\Doctor\TreatmentController::class, 'byMedicalRecord']);
        Route::get('{treatment}', [\App\Http\Controllers\Doctor\TreatmentController::class, 'show']);
        Route::put('{treatment}', [\App\Http\Controllers\Doctor\TreatmentController::class, 'update']);
        Route::delete('{treatment}', [\App\Http\Controllers\Doctor\TreatmentController::class, 'destroy']);
        Route::patch('{treatment}/administered', [\App\Http\Controllers\Doctor\TreatmentController::class, 'markAsAdministered']);
        Route::patch('{treatment}/completed', [\App\Http\Controllers\Doctor\TreatmentController::class, 'markAsCompleted']);
    });

    // Invoice Management (Doctor's own invoices)
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\App\Http\Controllers\Doctor\InvoiceController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Doctor\InvoiceController::class, 'store']);
        Route::get('/statistics', [\App\Http\Controllers\Doctor\InvoiceController::class, 'statistics']);
        Route::get('/{invoice}', [\App\Http\Controllers\Doctor\InvoiceController::class, 'show']);
        Route::put('/{invoice}', [\App\Http\Controllers\Doctor\InvoiceController::class, 'update']);
    });
});

// Authenticated user routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', \App\Http\Controllers\RoleController::class);
    
    // User pet management (users can only manage their own pets)
    Route::apiResource('pets', \App\Http\Controllers\PetController::class);

    // User medical history (read-only access to their pets' medical data)
    Route::prefix('my/pets')->group(function () {
        Route::get('medical-summary', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'myPetsWithMedicalSummary']);
        Route::get('{pet}/medical-history', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'petHistory']);
        Route::get('{pet}/medical-records/{medicalRecord}', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'getMedicalRecord']);
        Route::get('{pet}/medical-documents', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'petDocuments']);
        Route::get('{pet}/medical-documents/{document}', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'getDocument']);
        Route::get('{pet}/medical-documents/{document}/download', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'downloadDocument']);
        Route::get('{pet}/active-diagnoses', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'petActiveDiagnoses']);
        Route::get('{pet}/current-treatments', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'petCurrentTreatments']);
        Route::get('{pet}/upcoming-appointments', [\App\Http\Controllers\User\PetMedicalHistoryController::class, 'petUpcomingAppointments']);
    });

    // Invoice Management
    Route::prefix('invoices')->group(function () {
        Route::get('/', [\App\Http\Controllers\User\InvoiceController::class, 'index']);
        Route::get('/summary', [\App\Http\Controllers\User\InvoiceController::class, 'summary']);
        Route::get('/overdue', [\App\Http\Controllers\User\InvoiceController::class, 'overdue']);
        Route::get('/unpaid', [\App\Http\Controllers\User\InvoiceController::class, 'unpaid']);
        Route::get('/payment-history', [\App\Http\Controllers\User\InvoiceController::class, 'paymentHistory']);
        Route::get('/{invoice}', [\App\Http\Controllers\User\InvoiceController::class, 'show']);
        Route::get('/{invoice}/download-pdf', [\App\Http\Controllers\User\InvoiceController::class, 'downloadPdf']);
        Route::get('/{invoice}/print', [\App\Http\Controllers\User\InvoiceController::class, 'print']);
    });
    
    // Pet-specific invoices
    Route::get('/pets/{pet_id}/invoices', [\App\Http\Controllers\User\InvoiceController::class, 'petInvoices']);
    
    // Payment Processing
    Route::prefix('payments')->group(function () {
        Route::post('/online', [PaymentController::class, 'processOnlinePayment']);
        Route::get('/invoice/{invoice}', [PaymentController::class, 'invoicePayments']);
    });
});

// Calendar and availability routes (public for browsing)
Route::get('/calendar', [\App\Http\Controllers\AppointmentController::class, 'calendar']);
Route::get('/calendar/{doctor}', [\App\Http\Controllers\AppointmentController::class, 'getAvailableSlotsForDoctor']);
Route::get('/available-doctors', [\App\Http\Controllers\AppointmentController::class, 'getAvailableDoctors']);

// Appointment booking routes (authenticated users only)
Route::middleware('auth:sanctum')->group(function () {
    // Main appointment CRUD
    Route::apiResource('appointments', \App\Http\Controllers\AppointmentController::class);
    
    // Additional appointment routes
    Route::prefix('appointments')->group(function () {
        Route::get('upcoming/list', [\App\Http\Controllers\AppointmentController::class, 'upcoming']);
        Route::get('history/list', [\App\Http\Controllers\AppointmentController::class, 'history']);
        Route::patch('{appointment}/cancel', [\App\Http\Controllers\AppointmentController::class, 'cancel']);
    });
});

// Test route to verify doctor relationship (remove this after testing)
Route::middleware(['auth:sanctum', 'role:doctor'])->get('/test-doctor-relationship', function () {
    $user = auth()->user();
    $doctor = $user->doctor;
    
    return response()->json([
        'user_id' => $user->id,
        'user_name' => $user->name,
        'has_doctor_profile' => $doctor ? true : false,
        'doctor_id' => $doctor?->id,
        'doctor_specialization' => $doctor?->specialization,
    ]);
});

// ============================================================================
// BILLING SYSTEM ROUTES
// ============================================================================

// Services (Public - for browsing available services)
Route::prefix('services')->group(function () {
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/{service}', [ServiceController::class, 'show']);
    Route::get('/{service}/pricing', [ServiceController::class, 'pricing']);
    Route::get('/category/{category}', [ServiceController::class, 'byCategory']);
    Route::get('/emergency/list', [ServiceController::class, 'emergency']);
    Route::get('/categories/list', [ServiceController::class, 'categories']);
});

// Public Payment Routes (for invoice viewing and payment)
Route::prefix('invoices')->group(function () {
    Route::post('/{invoice}/mark-viewed', [\App\Http\Controllers\Admin\InvoiceController::class, 'markAsViewed']);
    Route::get('/{invoice}/payments', [PaymentController::class, 'invoicePayments']);
});
