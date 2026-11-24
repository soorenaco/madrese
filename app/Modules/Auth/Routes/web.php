<?php

use App\Modules\Auth\Controllers\Web\AuthController;
use App\Modules\Auth\Controllers\Web\UserManageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('auth.login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.login');
    Route::post('/login', [AuthController::class, 'requestOtp'])
        ->middleware(['throttle:auth-login-ip', 'throttle:auth-login'])
        ->name('auth.login.request');
    Route::post('/login/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:auth-verify')
        ->name('auth.login.verify');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('auth.dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::middleware('role:school_manager,admin')->group(function () {
        Route::get('/users', [UserManageController::class, 'index'])->name('panel.users');
        Route::put('/users/{user}/status', [UserManageController::class, 'updateStatus'])->name('panel.users.status');
        Route::post('/users/{user}/membership', [UserManageController::class, 'assignMembership'])->name('panel.users.membership');
    });

    Route::get('/settings', [\App\Modules\OnlineClass\Controllers\Web\SystemSettingsController::class, 'index'])
        ->middleware('role:admin')
        ->name('panel.settings');
        
});