<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_roles', function (Blueprint $table) {
            // عضویت مدرسه‌ای
            $table->uuid('membership_id');

            // نقش
            $table->uuid('role_id');

            // چه کسی این نقش را اعطا کرده
            $table->uuid('granted_by_user_id')->nullable();

            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            // کلید اصلی ترکیبی
            $table->primary(['membership_id', 'role_id']);

            // ایندکس برای جستجوی نقش‌های فعال
            $table->index(['membership_id', 'revoked_at']);

            // روابط
            $table->foreign('membership_id')
                  ->references('id')
                  ->on('school_memberships')
                  ->onDelete('cascade');

            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('restrict');

            $table->foreign('granted_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_roles');
    }
};