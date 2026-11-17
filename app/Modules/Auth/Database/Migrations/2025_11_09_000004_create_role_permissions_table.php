<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            // کلید خارجی به نقش
            $table->uuid('role_id');
            
            // کلید خارجی به مجوز
            $table->uuid('permission_id');

            /**
             * نوع تأثیر مجوز
             * - allow: اجازه دادن
             * - deny: صریحاً ممنوع کردن (deny اولویت بالاتری دارد)
             */
            $table->enum('effect', ['allow', 'deny'])->default('allow');

            $table->timestamps();

            // کلید اصلی ترکیبی
            $table->primary(['role_id', 'permission_id']);

            // روابط
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');

            $table->foreign('permission_id')
                  ->references('id')
                  ->on('permissions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};