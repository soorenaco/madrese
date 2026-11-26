<?php

use App\Modules\Attendance\Controllers\Web\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    // ثبت/مشاهده‌ی حضور و غیاب یک جلسه — معلم کلاس، مدیر مدرسه، ادمین
    Route::middleware('role:teacher,school_manager,admin')->group(function () {
        Route::get('/classes/{school_class}/attendance', [AttendanceController::class, 'show'])
            ->name('panel.classes.attendance');
        Route::post('/classes/{school_class}/attendance', [AttendanceController::class, 'store'])
            ->name('panel.classes.attendance.store');
    });

    // گزارش کلاس — دامنه‌ی وسیع‌تر (شامل دانش‌آموز برای گزارش خودش)
    Route::middleware('role:student,teacher,school_manager,admin')->group(function () {
        Route::get('/classes/{school_class}/attendance/report', [AttendanceController::class, 'report'])
            ->name('panel.classes.attendance.report');
    });
});
