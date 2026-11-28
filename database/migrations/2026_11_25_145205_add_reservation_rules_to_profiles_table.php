<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->unsignedSmallInteger('reservation_slot_capacity')->default(0)->after('loyalty_enabled');
            $table->unsignedTinyInteger('reservation_max_party')->default(20)->after('reservation_slot_capacity');
            $table->boolean('reservation_auto_confirm')->default(false)->after('reservation_max_party');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'reservation_slot_capacity',
                'reservation_max_party',
                'reservation_auto_confirm',
            ]);
        });
    }
};
