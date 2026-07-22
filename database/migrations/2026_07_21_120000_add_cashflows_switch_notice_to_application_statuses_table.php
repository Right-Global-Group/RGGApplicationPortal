<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            // Optional, admin-triggered: an application only gets this timestamp if
            // staff generate the Cashflows-to-Cardstream switch notice for it.
            $table->timestamp('cashflows_switch_notice_sent_at')->nullable()->after('contract_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn('cashflows_switch_notice_sent_at');
        });
    }
};
