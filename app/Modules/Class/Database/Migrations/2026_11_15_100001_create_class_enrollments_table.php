<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_class_id');
            $table->uuid('user_id');
            $table->enum('status', ['invited', 'active', 'ended'])->default('invited');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('school_class_id')
                ->references('id')
                ->on('school_classes')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->unique(['school_class_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['school_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
    }
};
