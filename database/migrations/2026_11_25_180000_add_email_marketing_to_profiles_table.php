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
            $table->boolean('email_marketing_enabled')->default(true)->after('facebook_page_access_token');
            $table->string('email_reply_to', 120)->nullable()->after('email_marketing_enabled');
            $table->text('email_footer')->nullable()->after('email_reply_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'email_marketing_enabled',
                'email_reply_to',
                'email_footer',
            ]);
        });
    }
};
