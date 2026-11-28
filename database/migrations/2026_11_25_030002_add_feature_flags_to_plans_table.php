<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_custom_domain')->default(false)->after('is_active');
            $table->boolean('has_analytics')->default(false)->after('has_custom_domain');
        });

        DB::table('plans')
            ->whereIn('plan_type', ['pro', 'premium'])
            ->update(['has_custom_domain' => true, 'has_analytics' => true]);
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['has_custom_domain', 'has_analytics']);
        });
    }
};
