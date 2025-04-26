<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix("/auth")->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/profile', function (Request $request) {
            return $request->user();
        });
    });
});

Route::prefix('users')->middleware("auth:sanctum")->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('{userId}', [UserController::class, 'show']);
    Route::put('{userId}', [UserController::class, 'update']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('roles', \App\Http\Controllers\RoleController::class);
    Route::apiResource('pets', \App\Http\Controllers\PetController::class);
});
