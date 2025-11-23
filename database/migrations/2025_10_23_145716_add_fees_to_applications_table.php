<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Remove location fields
            $table->dropColumn(['address', 'city', 'region', 'country', 'postal_code']);
            
            // Add fee fields
            $table->decimal('scaling_fee', 10, 2)->default(450.00)->after('name');
            $table->decimal('transaction_percentage', 5, 2)->default(2.00)->after('scaling_fee');
            $table->decimal('transaction_fixed_fee', 10, 2)->default(0.20)->after('transaction_percentage');
            $table->decimal('monthly_fee', 10, 2)->default(18.00)->after('transaction_fixed_fee');
            $table->decimal('monthly_minimum', 10, 2)->default(100.00)->after('monthly_fee');
            $table->decimal('service_fee', 10, 2)->default(10.00)->after('monthly_minimum');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Remove fee fields
            $table->dropColumn([
                'scaling_fee',
                'transaction_percentage',
                'transaction_fixed_fee',
                'monthly_fee',
                'monthly_minimum',
                'service_fee',
            ]);
            
            // Add back location fields
            $table->string('address', 150)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('postal_code', 25)->nullable();
        });
    }
};