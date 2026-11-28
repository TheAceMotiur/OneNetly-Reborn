<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->string('public_key', 40)->nullable()->unique()->after('name');
        });

        DB::table('widgets')
            ->select('id')
            ->whereNull('public_key')
            ->orderBy('id')
            ->chunkById(100, function ($widgets): void {
                foreach ($widgets as $widget) {
                    DB::table('widgets')
                        ->where('id', $widget->id)
                        ->update(['public_key' => Str::random(40)]);
                }
            });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('widgets', function (Blueprint $table) {
            $table->dropUnique(['public_key']);
            $table->dropColumn('public_key');
        });
    }
};
