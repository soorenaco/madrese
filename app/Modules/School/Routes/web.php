<?php

use App\Modules\School\Controllers\Web\SchoolController;
use App\Modules\School\Controllers\Web\SchoolDeputyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::middleware('role:school_manager,admin')->group(function () {
        // مدرسه
        Route::get('/school', [SchoolController::class, 'index'])->name('panel.school');
        Route::get('/school/create', [SchoolController::class, 'create'])->name('panel.school.create');
        Route::post('/school', [SchoolController::class, 'store'])->name('panel.school.store');
        Route::get('/school/{school}/edit', [SchoolController::class, 'edit'])->name('panel.school.edit');
        Route::put('/school/{school}', [SchoolController::class, 'update'])->name('panel.school.update');
        Route::delete('/school/{school}', [SchoolController::class, 'destroy'])->name('panel.school.destroy');

        // معاون‌ها
        Route::get('/school/{school}/deputies', [SchoolDeputyController::class, 'show'])->name('panel.school.deputies');
        Route::post('/school/{school}/primary-manager', [SchoolDeputyController::class, 'setPrimary'])->name('panel.school.primary');
        Route::post('/school/{school}/deputy-limits', [SchoolDeputyController::class, 'setLimits'])->name('panel.school.deputy-limits');
        Route::post('/school/{school}/deputies', [SchoolDeputyController::class, 'addDeputy'])->name('panel.school.deputies.add');
    });
});