<?php

use App\Modules\Class\Controllers\Api\ClassPromoteController;
use App\Modules\Class\Controllers\Api\ClassRosterController;
use App\Modules\Class\Controllers\Api\ClassScheduleController;
use App\Modules\Class\Controllers\Api\SchoolClassController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // ─── کلاس‌ها (CRUD) ───
    Route::middleware('role:teacher,school_manager,admin')->group(function () {
        Route::get('/classes', [SchoolClassController::class, 'index']);
        Route::get('/classes/{class}', [SchoolClassController::class, 'show'])->whereUuid('class');
    });

    Route::middleware('role:school_manager,admin')->group(function () {
        Route::post('/classes', [SchoolClassController::class, 'store']);
        Route::put('/classes/{class}', [SchoolClassController::class, 'update'])->whereUuid('class');
        Route::delete('/classes/{class}', [SchoolClassController::class, 'destroy'])->whereUuid('class');

        // ─── Roster ───
        Route::get('/classes/{class}/roster', [ClassRosterController::class, 'index'])->whereUuid('class');
        Route::post('/classes/{class}/students', [ClassRosterController::class, 'inviteStudent'])->whereUuid('class');
        Route::delete('/classes/{class}/students/{user}', [ClassRosterController::class, 'removeStudent'])->whereUuid('class')->whereUuid('user');
        Route::post('/classes/{class}/students/{user}/restore', [ClassRosterController::class, 'restoreStudent'])->whereUuid('class')->whereUuid('user');
        Route::put('/classes/{class}/students/{user}/status', [ClassRosterController::class, 'updateStudentStatus'])->whereUuid('class')->whereUuid('user');
        Route::post('/classes/{class}/teacher', [ClassRosterController::class, 'assignTeacher'])->whereUuid('class');

        // ─── Schedule ───
        Route::get('/classes/{class}/schedule', [ClassScheduleController::class, 'index'])->whereUuid('class');
        Route::post('/classes/{class}/schedule', [ClassScheduleController::class, 'store'])->whereUuid('class');

        // ─── Promote ───
        Route::post('/classes/{class}/promote', [ClassPromoteController::class, 'store'])->whereUuid('class');
    });
});