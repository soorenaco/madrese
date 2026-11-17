<?php

use App\Modules\Auth\Controllers\Api\AuthController;
use App\Modules\Auth\Controllers\Api\UserManageController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // روت‌های عمومی — با Rate Limit
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware(['throttle:auth-login-ip', 'throttle:auth-login']);
    Route::post('/verify', [AuthController::class, 'verify'])
        ->middleware('throttle:auth-verify');

    // روت‌های محافظت‌شده با Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

// ─── مدیریت کاربران (مدیر مدرسه / ادمین) ───
Route::middleware(['auth:sanctum', 'role:school_manager,admin'])->group(function () {
    Route::get('/users', [UserManageController::class, 'index']);
    Route::put('/users/{user}/status', [UserManageController::class, 'updateStatus'])->whereUuid('user');
    Route::post('/users/{user}/membership', [UserManageController::class, 'assignMembership'])->whereUuid('user');
});