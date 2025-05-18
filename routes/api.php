<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

        Route::put('{user}/promote', [\App\Http\Controllers\DoctorController::class, 'promoteToDoctor'])
            ->middleware('role:admin')
            ->name('promote.doctor');
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', \App\Http\Controllers\RoleController::class);
    Route::apiResource('pets', \App\Http\Controllers\PetController::class);
});

Route::get('/doctors/{doctor}/available-slots', [\App\Http\Controllers\AppointmentController::class, 'index']);
Route::get('/calendar', [\App\Http\Controllers\AppointmentController::class, 'calendar']);
Route::get('/calendar-by-doctor', [\App\Http\Controllers\AppointmentController::class, 'getAvailableDoctors']);
