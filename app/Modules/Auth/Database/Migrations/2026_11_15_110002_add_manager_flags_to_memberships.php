<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_memberships', function (Blueprint $table) {
            // مدیر اصلی مدرسه
            $table->boolean('is_primary_manager')->default(false)->after('status');
            // محدودیت‌های معاون (null = بدون محدودیت / مدیر اصلی)
            $table->json('manager_limits')->nullable()->after('is_primary_manager');
        });
    }

    public function down(): void
    {
        Schema::table('school_memberships', function (Blueprint $table) {
            $table->dropColumn(['is_primary_manager', 'manager_limits']);
        });
    }
};
