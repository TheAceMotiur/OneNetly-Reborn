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
            $table->boolean('facebook_enabled')->default(false)->after('whatsapp_reservation_updates');
            $table->string('facebook_page_name', 120)->nullable()->after('facebook_enabled');
            $table->string('facebook_page_id', 64)->nullable()->after('facebook_page_name');
            $table->text('facebook_page_access_token')->nullable()->after('facebook_page_id');
            $table->index('facebook_page_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['facebook_page_id']);
            $table->dropColumn([
                'facebook_enabled',
                'facebook_page_name',
                'facebook_page_id',
                'facebook_page_access_token',
            ]);
        });
    }
};
