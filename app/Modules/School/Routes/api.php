<?php

use App\Modules\School\Controllers\Api\SchoolController;
use App\Modules\School\Controllers\Api\SchoolDeputyController;

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // فقط ادمین می‌سازد و حذف می‌کند
    Route::middleware('role:admin')->group(function () {
        Route::post('/schools', [SchoolController::class, 'store']);
        Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->whereUuid('school');
    });

    // مدیر مدرسه فقط مدرسهٔ خودش (در کنترلر بررسی می‌شود)
    Route::middleware('role:school_manager,admin')->group(function () {
        Route::get('/schools', [SchoolController::class, 'index']);
        Route::get('/schools/{school}', [SchoolController::class, 'show'])->whereUuid('school');
        Route::put('/schools/{school}', [SchoolController::class, 'update'])->whereUuid('school');
    });

        // ─── معاون‌ها ───
    Route::middleware('role:school_manager,admin')->group(function () {
        Route::get('/schools/{school}/deputies', [SchoolDeputyController::class, 'index'])->whereUuid('school');
        Route::post('/schools/{school}/primary-manager', [SchoolDeputyController::class, 'setPrimary'])->whereUuid('school');
        Route::post('/schools/{school}/deputy-limits', [SchoolDeputyController::class, 'setLimits'])->whereUuid('school');
    });

});