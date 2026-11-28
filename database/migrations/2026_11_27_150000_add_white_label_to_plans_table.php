<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_white_label')->default(false)->after('has_analytics');
        });

        // Paid website plans hide OneNetly branding; free and tool plans keep it.
        DB::table('plans')
            ->whereIn('plan_type', ['pro', 'premium'])
            ->update(['has_white_label' => true]);
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_white_label');
        });
    }
};
