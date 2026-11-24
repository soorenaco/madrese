<?php

use App\Modules\OnlineClass\Controllers\Web\OnlineClassController;
use App\Modules\OnlineClass\Controllers\Web\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {

    // فهرست کلاس‌های آنلاین (پنل دانش‌آموز/معلم/مدیر)
    Route::get('/online-classes', [OnlineClassController::class, 'index'])->name('panel.online-classes');

    Route::middleware('role:teacher,school_manager,admin')->group(function () {
        Route::get('/classes/{school_class}/online', [OnlineClassController::class, 'show'])->name('panel.classes.online');
        Route::post('/classes/{school_class}/online', [OnlineClassController::class, 'store'])->name('panel.classes.online.store');
        Route::put('/classes/{school_class}/online', [OnlineClassController::class, 'update'])->name('panel.classes.online.update');
        Route::delete('/classes/{school_class}/online', [OnlineClassController::class, 'destroy'])->name('panel.classes.online.destroy');
        Route::post('/classes/{school_class}/online/start', [OnlineClassController::class, 'start'])->name('panel.classes.online.start');
        Route::post('/classes/{school_class}/online/end', [OnlineClassController::class, 'end'])->name('panel.classes.online.end');
    });

    Route::middleware('role:student,teacher,school_manager,admin')->group(function () {
        Route::get('/classes/{school_class}/online/join', [OnlineClassController::class, 'join'])->name('panel.classes.online.join');
    });

    // ─── پیکربندی ارائه‌دهنده‌ها (فقط ادمین) ───
    Route::middleware('role:admin')->group(function () {
        Route::put('/settings/providers', [SystemSettingsController::class, 'updateProviders'])->name('panel.settings.providers');
    });
});
