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
        if (! Schema::hasColumn('whatsapp_automations', 'name')) {
            Schema::table('whatsapp_automations', function (Blueprint $table) {
                $table->string('name', 80)->nullable()->after('key');
            });
        }

        if (! Schema::hasColumn('whatsapp_automations', 'keywords')) {
            Schema::table('whatsapp_automations', function (Blueprint $table) {
                $table->string('keywords', 500)->nullable()->after('name');
            });
        }

        // MySQL keeps the foreign key on profile_id using the unique index, so give
        // the column its own index before dropping that constraint.
        Schema::table('whatsapp_automations', function (Blueprint $table) {
            $table->index('profile_id');
        });

        Schema::table('whatsapp_automations', function (Blueprint $table) {
            $table->dropUnique(['profile_id', 'key']);
        });

        Schema::table('whatsapp_automations', function (Blueprint $table) {
            $table->index(['profile_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_automations', function (Blueprint $table) {
            $table->dropIndex(['profile_id', 'key']);
            $table->unique(['profile_id', 'key']);
        });

        Schema::table('whatsapp_automations', function (Blueprint $table) {
            $table->dropColumn(['name', 'keywords']);
        });
    }
};
