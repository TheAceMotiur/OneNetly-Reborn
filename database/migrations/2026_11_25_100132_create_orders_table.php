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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('customer_name', 80);
            $table->string('customer_phone', 40);
            $table->string('customer_email', 120)->nullable();
            $table->string('order_type', 20)->default('dine_in');
            $table->string('table_number', 20)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('total_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['profile_id', 'created_at']);
            $table->index(['profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
