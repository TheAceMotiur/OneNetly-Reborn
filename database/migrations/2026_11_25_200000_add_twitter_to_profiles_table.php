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
            $table->boolean('twitter_enabled')->default(false)->after('instagram_access_token');
            $table->string('twitter_username', 50)->nullable()->after('twitter_enabled');
            $table->string('twitter_user_id', 32)->nullable()->after('twitter_username');
            $table->text('twitter_access_token')->nullable()->after('twitter_user_id');
            $table->text('twitter_refresh_token')->nullable()->after('twitter_access_token');
            $table->timestamp('twitter_token_expires_at')->nullable()->after('twitter_refresh_token');
            $table->index('twitter_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropIndex(['twitter_user_id']);
            $table->dropColumn([
                'twitter_enabled',
                'twitter_username',
                'twitter_user_id',
                'twitter_access_token',
                'twitter_refresh_token',
                'twitter_token_expires_at',
            ]);
        });
    }
};
