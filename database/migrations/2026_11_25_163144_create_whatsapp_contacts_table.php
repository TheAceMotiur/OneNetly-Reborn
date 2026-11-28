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
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 40);
            $table->string('name', 80)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('source', 30)->default('manual');
            $table->boolean('opted_in')->default(true);
            $table->timestamp('last_messaged_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'phone']);
            $table->index(['profile_id', 'opted_in']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_contacts');
    }
};
