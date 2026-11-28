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
            $table->boolean('instagram_enabled')->default(false)->after('email_footer');
            $table->string('instagram_username', 120)->nullable()->after('instagram_enabled');
            $table->string('instagram_account_id', 64)->nullable()->after('instagram_username');
            $table->text('instagram_access_token')->nullable()->after('instagram_account_id');
            $table->index('instagram_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['instagram_account_id']);
            $table->dropColumn([
                'instagram_enabled',
                'instagram_username',
                'instagram_account_id',
                'instagram_access_token',
            ]);
        });
    }
};
