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
            $table->boolean('whatsapp_enabled')->default(false)->after('sms_notifications_enabled');
            $table->string('whatsapp_display_number', 40)->nullable()->after('whatsapp_enabled');
            $table->string('whatsapp_phone_number_id', 64)->nullable()->after('whatsapp_display_number');
            $table->string('whatsapp_business_account_id', 64)->nullable()->after('whatsapp_phone_number_id');
            $table->text('whatsapp_access_token')->nullable()->after('whatsapp_business_account_id');
            $table->boolean('whatsapp_order_updates')->default(true)->after('whatsapp_access_token');
            $table->boolean('whatsapp_reservation_updates')->default(true)->after('whatsapp_order_updates');

            $table->index('whatsapp_phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_phone_number_id']);
            $table->dropColumn([
                'whatsapp_enabled',
                'whatsapp_display_number',
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
                'whatsapp_access_token',
                'whatsapp_order_updates',
                'whatsapp_reservation_updates',
            ]);
        });
    }
};
