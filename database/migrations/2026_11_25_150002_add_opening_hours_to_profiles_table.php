<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->json('opening_hours')->nullable()->after('service_rate');
            $table->string('timezone', 64)->default('UTC')->after('opening_hours');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['opening_hours', 'timezone']);
        });
    }
};
