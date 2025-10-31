<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->enum('gateway_partner', ['cardstream', 'acquired'])->nullable()->after('trading_name');
            $table->string('gateway_mid')->nullable()->after('gateway_partner');
            $table->json('gateway_integration_details')->nullable()->after('gateway_mid');
            $table->string('wordpress_url')->nullable()->after('gateway_integration_details');
            $table->string('wordpress_admin_email')->nullable()->after('wordpress_url');
            $table->string('wordpress_admin_username')->nullable()->after('wordpress_admin_email');
        });

        Schema::table('application_statuses', function (Blueprint $table) {
            $table->timestamp('gateway_contract_sent_at')->nullable()->after('contract_submitted_at');
            $table->timestamp('gateway_contract_signed_at')->nullable()->after('gateway_contract_sent_at');
            $table->timestamp('wordpress_credentials_collected_at')->nullable()->after('gateway_contract_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_partner',
                'gateway_mid',
                'gateway_integration_details',
                'wordpress_url',
                'wordpress_admin_email',
                'wordpress_admin_username',
            ]);
        });

        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn([
                'gateway_contract_sent_at',
                'gateway_contract_signed_at',
                'wordpress_credentials_collected_at',
            ]);
        });
    }
};