<?php

use App\Modules\OnlineClass\Controllers\Api\OnlineClassController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // فهرست کلاس‌های آنلاین قابل‌مشاهده توسط کاربر (ورود دانش‌آموز از اینجا)
    Route::get('/online-classes', [OnlineClassController::class, 'index']);

    // ─── مدیریت کلاس آنلاین (مدیر / معاون مجاز / معلم کلاس) ───
    Route::middleware('role:teacher,school_manager,admin')->group(function () {
        Route::get('/classes/{class}/online', [OnlineClassController::class, 'show'])->whereUuid('class');
        Route::post('/classes/{class}/online', [OnlineClassController::class, 'store'])->whereUuid('class');
        Route::put('/classes/{class}/online', [OnlineClassController::class, 'update'])->whereUuid('class');
        Route::delete('/classes/{class}/online', [OnlineClassController::class, 'destroy'])->whereUuid('class');
        Route::post('/classes/{class}/online/start', [OnlineClassController::class, 'start'])->whereUuid('class');
        Route::post('/classes/{class}/online/end', [OnlineClassController::class, 'end'])->whereUuid('class');
    });

    // ─── ورود به جلسه (دانش‌آموز + معلم + مدیر/معاون) ───
    Route::middleware('role:student,teacher,school_manager,admin')->group(function () {
        Route::get('/classes/{class}/online/join', [OnlineClassController::class, 'join'])->whereUuid('class');
    });
});
