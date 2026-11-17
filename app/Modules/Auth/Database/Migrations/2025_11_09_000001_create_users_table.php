<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // شماره موبایل به فرمت E.164 (مثال: +989123456789)
            $table->string('mobile_e164', 20)->unique();
            
            $table->string('display_name', 160);
            
            // وضعیت حساب
            $table->enum('status', ['invited', 'active', 'blocked'])
                  ->default('invited');
            
            $table->timestamp('last_login_at')->nullable();
            
            $table->timestamps();

            // ایندکس‌های ضروری
            $table->index('status');
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};