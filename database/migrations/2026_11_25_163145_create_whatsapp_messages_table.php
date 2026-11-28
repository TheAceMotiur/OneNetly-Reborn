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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('contact_phone', 40);
            $table->string('contact_name', 80)->nullable();
            $table->string('direction', 10)->default('outbound');
            $table->string('message_type', 20)->default('text');
            $table->text('body')->nullable();
            $table->string('template_name', 80)->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('external_id', 80)->nullable();
            $table->string('error', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'created_at']);
            $table->index('contact_phone');
            $table->index('external_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
