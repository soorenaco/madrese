<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('city');
            $table->string('email', 120)->nullable()->after('phone');
            $table->string('website', 200)->nullable()->after('email');
            $table->text('description')->nullable()->after('website');
            $table->string('logo', 255)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email', 'website', 'description', 'logo']);
        });
    }
};