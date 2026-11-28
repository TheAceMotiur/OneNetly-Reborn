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
        Schema::create('whatsapp_automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->boolean('is_enabled')->default(true);
            $table->text('message')->nullable();
            $table->string('template_name', 80)->nullable();
            $table->string('language', 10)->default('en_US');
            $table->timestamps();

            $table->unique(['profile_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_automations');
    }
};
