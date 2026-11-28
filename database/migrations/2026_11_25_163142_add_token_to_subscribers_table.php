<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('token', 48)->nullable()->unique()->after('email');
        });

        DB::table('subscribers')
            ->whereNull('token')
            ->orderBy('id')
            ->each(function (object $subscriber): void {
                DB::table('subscribers')
                    ->where('id', $subscriber->id)
                    ->update(['token' => Str::random(48)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn('token');
        });
    }
};
