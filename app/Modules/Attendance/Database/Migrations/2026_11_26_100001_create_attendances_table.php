<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_class_id');
            $table->uuid('user_id');                 // دانش‌آموز
            $table->date('session_date');            // تاریخ جلسه (میلادی)
            $table->unsignedTinyInteger('slot')->default(1); // شماره جلسه در روز (۱، ۲، ...)
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->uuid('recorded_by')->nullable(); // کاربری که ثبت کرده (معلم/مدیر)
            $table->string('note', 300)->nullable(); // توضیح (مثلاً عذر غیبت)
            $table->timestamps();

            $table->foreign('school_class_id')
                ->references('id')
                ->on('school_classes')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('recorded_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // یک دانش‌آموز در یک جلسه‌ی یک کلاس فقط یک رکورد دارد
            $table->unique(['school_class_id', 'user_id', 'session_date', 'slot'], 'attendance_session_unique');

            $table->index(['school_class_id', 'session_date']);
            $table->index(['user_id', 'session_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
