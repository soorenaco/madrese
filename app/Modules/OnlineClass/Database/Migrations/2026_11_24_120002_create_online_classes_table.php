<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * کلاس آنلاین یک مدرسه — یک رکورد به ازای هر school_class.
 * provider_room_id: BBB → meetingID (GUID) | Skyroom → room_id (عددی).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_class_id');
            $table->enum('provider', ['bbb', 'skyroom']);
            $table->string('title', 160)->nullable();
            $table->string('provider_room_id', 200)->nullable();
            $table->enum('status', ['idle', 'live', 'ended', 'cancelled'])->default('idle');
            $table->boolean('record')->default(false);
            $table->string('recording_url', 500)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('school_class_id')
                ->references('id')
                ->on('school_classes')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // هر کلاس فقط یک کلاس آنلاین دارد.
            $table->unique('school_class_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_classes');
    }
};
