<?php

use App\Modules\Class\Controllers\Web\ClassPromoteController;
use App\Modules\Class\Controllers\Web\ClassRosterController;
use App\Modules\Class\Controllers\Web\ClassScheduleController;
use App\Modules\Class\Controllers\Web\SchoolClassController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('role:teacher,school_manager,admin')->group(function () {
        Route::get('/classes', [SchoolClassController::class, 'index'])->name('panel.classes');
        Route::get('/classes/create', [SchoolClassController::class, 'create'])->name('panel.classes.create');
        Route::post('/classes', [SchoolClassController::class, 'store'])->name('panel.classes.store');
        Route::get('/classes/{school_class}/edit', [SchoolClassController::class, 'edit'])->name('panel.classes.edit');
        Route::put('/classes/{school_class}', [SchoolClassController::class, 'update'])->name('panel.classes.update');
        Route::delete('/classes/{school_class}', [SchoolClassController::class, 'destroy'])->name('panel.classes.destroy');
    });

    Route::middleware('role:school_manager,admin')->group(function () {
        Route::get('/classes/{school_class}/roster', [ClassRosterController::class, 'show'])->name('panel.classes.roster');
        Route::post('/classes/{school_class}/students', [ClassRosterController::class, 'inviteStudent'])->name('panel.classes.students.invite');
        Route::delete('/classes/{school_class}/students/{user}', [ClassRosterController::class, 'removeStudent'])->name('panel.classes.students.remove');
        Route::post('/classes/{school_class}/students/{user}/restore', [ClassRosterController::class, 'restoreStudent'])->name('panel.classes.students.restore');
        Route::put('/classes/{school_class}/students/{user}/status', [ClassRosterController::class, 'updateStudentStatus'])->name('panel.classes.students.status');
        Route::post('/classes/{school_class}/teacher', [ClassRosterController::class, 'assignTeacher'])->name('panel.classes.teacher');

        Route::get('/classes/{school_class}/promote', [ClassPromoteController::class, 'show'])->name('panel.classes.promote');
        Route::post('/classes/{school_class}/promote', [ClassPromoteController::class, 'store'])->name('panel.classes.promote.store');

        Route::get('/classes/{school_class}/schedule', [ClassScheduleController::class, 'show'])->name('panel.classes.schedule');
        Route::post('/classes/{school_class}/schedule', [ClassScheduleController::class, 'store'])->name('panel.classes.schedule.store');
    });
});