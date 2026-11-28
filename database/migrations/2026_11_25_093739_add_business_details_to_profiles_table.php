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
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('business_type', 30)->default('personal')->after('template');
            $table->string('company', 80)->nullable()->after('business_type');
            $table->string('job_title', 80)->nullable()->after('company');
            $table->string('contact_email', 120)->nullable()->after('job_title');
            $table->string('phone', 40)->nullable()->after('contact_email');
            $table->string('contact_website', 255)->nullable()->after('phone');
            $table->string('address', 255)->nullable()->after('contact_website');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn([
                'business_type', 'company', 'job_title', 'contact_email',
                'phone', 'contact_website', 'address',
            ]);
        });
    }
};
