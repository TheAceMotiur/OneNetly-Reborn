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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_address', 255)->nullable()->after('table_number');
            $table->unsignedInteger('delivery_fee_cents')->default(0)->after('subtotal_cents');
            $table->unsignedInteger('service_cents')->default(0)->after('delivery_fee_cents');
            $table->unsignedInteger('tax_cents')->default(0)->after('service_cents');
            $table->unsignedInteger('tip_cents')->default(0)->after('tax_cents');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_address',
                'delivery_fee_cents',
                'service_cents',
                'tax_cents',
                'tip_cents',
            ]);
        });
    }
};
