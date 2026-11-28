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
            $table->boolean('accepting_orders')->default(true)->after('menu_ordering_enabled');
            $table->string('pos_webhook_url', 255)->nullable()->after('menu_currency');
            $table->string('pos_webhook_secret', 64)->nullable()->after('pos_webhook_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['accepting_orders', 'pos_webhook_url', 'pos_webhook_secret']);
        });
    }
};
