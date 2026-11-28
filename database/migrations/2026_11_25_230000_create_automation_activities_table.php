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
        Schema::create('automation_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 20);
            $table->string('automation_key', 40);
            $table->string('rule_name', 80)->nullable();
            $table->string('trigger', 40);
            $table->string('contact', 191)->nullable();
            $table->string('contact_name', 120)->nullable();
            $table->string('status', 20);
            $table->string('detail', 500)->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'tool', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_activities');
    }
};
