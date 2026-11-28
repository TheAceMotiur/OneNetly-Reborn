<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('profiles')
            ->whereNotNull('custom_domain')
            ->whereNull('custom_domain_token')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id): void {
                DB::table('profiles')
                    ->where('id', $id)
                    ->update(['custom_domain_token' => Str::random(32)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
