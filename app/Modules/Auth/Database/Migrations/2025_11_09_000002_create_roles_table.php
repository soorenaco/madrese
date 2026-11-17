<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // کد یکتای نقش (مثلاً: school_manager, teacher, student)
            $table->string('code', 60)->unique();

            // عنوان فارسی نقش
            $table->string('title', 100);

            /**
             * دامنه دسترسی نقش
             * این فیلد خیلی مهمه و دقیقاً طبق سند مشتری تعریف شده:
             * - platform: سطح کل سیستم
             * - school: سطح مدرسه
             * - class: سطح کلاس
             * - self: فقط خودش
             * - linked_child: فرزند متصل‌شده (برای والدین)
             * - assigned_students: دانش‌آموزان تخصیص‌یافته (برای مشاور و بازار)
             */
            $table->enum('scope', [
                'platform',
                'school',
                'class',
                'self',
                'linked_child',
                'assigned_students'
            ]);

            // آیا این نقش سیستمی است؟ (نقش‌های سیستمی نباید توسط کاربر عادی حذف شوند)
            $table->boolean('is_system')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};