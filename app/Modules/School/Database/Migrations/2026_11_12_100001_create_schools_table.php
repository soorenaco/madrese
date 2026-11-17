<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('code', 40)->nullable()->unique();
            $table->string('city', 100)->nullable();
            $table->string('address', 500)->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};