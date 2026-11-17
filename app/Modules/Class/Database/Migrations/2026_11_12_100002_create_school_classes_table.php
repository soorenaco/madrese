<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id');
            $table->string('name', 120);
            $table->string('grade', 40)->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->uuid('teacher_user_id')->nullable();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            $table->foreign('teacher_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['school_id', 'status']);
            $table->unique(['school_id', 'name', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};