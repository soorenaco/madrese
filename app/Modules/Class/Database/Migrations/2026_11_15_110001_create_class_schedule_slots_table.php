<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedule_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_class_id');
            // 0 = شنبه ... 6 = جمعه (تقویم ایران)
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room', 80)->nullable();
            $table->timestamps();

            $table->foreign('school_class_id')
                ->references('id')
                ->on('school_classes')
                ->onDelete('cascade');

            $table->index(['school_class_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_slots');
    }
};
