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
        Schema::create('twitter_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 80)->nullable();
            $table->string('keywords', 500)->nullable();
            $table->text('message')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['profile_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twitter_automations');
    }
};
