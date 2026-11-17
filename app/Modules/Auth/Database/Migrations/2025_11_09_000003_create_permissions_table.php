<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            /**
             * کد یکتای مجوز
             * استاندارد نام‌گذاری: resource.action
             * مثال‌ها:
             * - lesson_session.read
             * - assignment.publish
             * - attendance.update
             * - user.invite
             */
            $table->string('code', 100)->unique();

            // منبعی که این مجوز روی آن اعمال می‌شود
            $table->string('resource', 60);

            // عملی که مجاز است (read, create, update, delete, publish, ...)
            $table->string('action', 60);

            // توضیح کامل برای تیم و داکیومنت
            $table->text('description');

            $table->timestamps();

            // ایندکس ترکیبی برای جستجوی سریع
            $table->index(['resource', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};