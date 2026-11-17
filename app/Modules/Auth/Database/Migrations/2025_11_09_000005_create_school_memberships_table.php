<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // مدرسه مربوطه
            $table->uuid('school_id');

            // کاربر مربوطه
            $table->uuid('user_id');

            /**
             * وضعیت عضویت در مدرسه
             * - invited: دعوت شده ولی هنوز فعال نشده
             * - active: عضویت فعال
             * - suspended: تعلیق شده
             * - ended: عضویت پایان یافته
             */
            $table->enum('status', ['invited', 'active', 'suspended', 'ended'])
                  ->default('invited');

            $table->timestamp('joined_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            // یک کاربر در هر مدرسه فقط یک عضویت می‌تواند داشته باشد
            $table->unique(['school_id', 'user_id']);

            // ایندکس‌های ضروری برای پرفورمنس و امنیت
            $table->index(['user_id', 'status']);
            $table->index(['school_id', 'status']);

            // روابط
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // توجه: جدول schools هنوز ساخته نشده.
            // فعلاً foreign key آن را کامنت می‌کنیم و بعداً اضافه می‌کنیم.
            // $table->foreign('school_id')->references('id')->on('schools')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_memberships');
    }
};