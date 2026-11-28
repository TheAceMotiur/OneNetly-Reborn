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
            $table->boolean('delivery_enabled')->default(true)->after('accepting_orders');
            $table->unsignedInteger('delivery_fee_cents')->default(0)->after('delivery_enabled');
            $table->unsignedInteger('min_order_cents')->default(0)->after('delivery_fee_cents');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('min_order_cents');
            $table->decimal('service_rate', 5, 2)->default(0)->after('tax_rate');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_enabled',
                'delivery_fee_cents',
                'min_order_cents',
                'tax_rate',
                'service_rate',
            ]);
        });
    }
};
