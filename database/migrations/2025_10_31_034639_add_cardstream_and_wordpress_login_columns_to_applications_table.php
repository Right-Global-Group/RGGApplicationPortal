<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // CardStream credentials
            if (!Schema::hasColumn('applications', 'cardstream_username')) {
                $table->string('cardstream_username')->nullable()->after('service_fee');
            }
            if (!Schema::hasColumn('applications', 'cardstream_password')) {
                $table->string('cardstream_password')->nullable()->after('cardstream_username');
            }
            if (!Schema::hasColumn('applications', 'cardstream_merchant_id')) {
                $table->string('cardstream_merchant_id')->nullable()->after('cardstream_password');
            }
            if (!Schema::hasColumn('applications', 'cardstream_credentials_entered_at')) {
                $table->timestamp('cardstream_credentials_entered_at')->nullable()->after('cardstream_merchant_id');
            }
            
            // WordPress credentials
            if (!Schema::hasColumn('applications', 'wordpress_username')) {
                $table->string('wordpress_username')->nullable()->after('wordpress_url');
            }
            if (!Schema::hasColumn('applications', 'wordpress_password')) {
                $table->string('wordpress_password')->nullable()->after('wordpress_username');
            }
            if (!Schema::hasColumn('applications', 'wordpress_credentials_entered_at')) {
                $table->timestamp('wordpress_credentials_entered_at')->nullable()->after('wordpress_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'cardstream_username')) {
                $table->dropColumn('cardstream_username');
            }
            if (Schema::hasColumn('applications', 'cardstream_password')) {
                $table->dropColumn('cardstream_password');
            }
            if (Schema::hasColumn('applications', 'cardstream_merchant_id')) {
                $table->dropColumn('cardstream_merchant_id');
            }
            if (Schema::hasColumn('applications', 'cardstream_credentials_entered_at')) {
                $table->dropColumn('cardstream_credentials_entered_at');
            }
            if (Schema::hasColumn('applications', 'wordpress_username')) {
                $table->dropColumn('wordpress_username');
            }
            if (Schema::hasColumn('applications', 'wordpress_password')) {
                $table->dropColumn('wordpress_password');
            }
            if (Schema::hasColumn('applications', 'wordpress_credentials_entered_at')) {
                $table->dropColumn('wordpress_credentials_entered_at');
            }
        });
    }
};