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
        Schema::create('instagram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('igsid', 64)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('channel', 20)->default('messenger');
            $table->string('direction', 20)->default('inbound');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('received');
            $table->string('external_id', 120)->nullable();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'channel']);
            $table->index(['profile_id', 'igsid']);
            $table->index('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instagram_messages');
    }
};
