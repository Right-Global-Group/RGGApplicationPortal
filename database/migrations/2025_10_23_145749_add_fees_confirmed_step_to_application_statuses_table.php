<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the enum to include 'fees_confirmed' step
        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'fees_confirmed',
            'application_sent',
            'contract_completed',
            'contract_submitted',
            'application_approved',
            'approval_email_sent',
            'invoice_sent',
            'invoice_paid',
            'gateway_integrated',
            'account_live'
        ) DEFAULT 'created'");
        
        // Add timestamp for fees_confirmed step
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->timestamp('fees_confirmed_at')->nullable()->after('contract_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn('fees_confirmed_at');
        });
        
        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'application_sent',
            'contract_completed',
            'contract_submitted',
            'application_approved',
            'approval_email_sent',
            'invoice_sent',
            'invoice_paid',
            'gateway_integrated',
            'account_live'
        ) DEFAULT 'created'");
    }
};