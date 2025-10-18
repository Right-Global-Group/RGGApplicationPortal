<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('trading_name')->nullable();
            $table->string('company_number')->nullable();
            $table->enum('business_type', ['sole_trader', 'partnership', 'limited', 'llp', 'other'])->nullable();
            $table->date('trading_start_date')->nullable();
            $table->decimal('estimated_annual_turnover', 12, 2)->nullable();
            $table->string('website_url')->nullable();
            $table->boolean('pci_compliant')->default(false);
            $table->string('gateway_preference')->nullable();
        });
    }

    public function down()
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'trading_name',
                'company_number',
                'business_type',
                'trading_start_date',
                'estimated_annual_turnover',
                'website_url',
                'pci_compliant',
                'gateway_preference'
            ]);
        });
    }
};
