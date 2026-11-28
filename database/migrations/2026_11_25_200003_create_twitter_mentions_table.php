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
        Schema::create('twitter_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('tweet_id', 40);
            $table->string('author_id', 40)->nullable();
            $table->string('author_username', 50)->nullable();
            $table->text('body')->nullable();
            $table->boolean('replied')->default(false);
            $table->string('reply_tweet_id', 40)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'tweet_id']);
            $table->index(['profile_id', 'replied']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twitter_mentions');
    }
};
