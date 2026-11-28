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
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->string('email', 120)->nullable()->after('name');
            $table->unsignedBigInteger('total_spent_cents')->default(0)->after('visits');
            $table->text('notes')->nullable()->after('total_spent_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loyalty_accounts', function (Blueprint $table) {
            $table->dropColumn(['email', 'total_spent_cents', 'notes']);
        });
    }
};
