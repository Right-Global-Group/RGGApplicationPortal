<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * current_step is a MySQL ENUM, so the new optional step has to be added to its
     * allowed values explicitly or transitionTo() truncates it to an empty string.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'contract_sent',
            'documents_uploaded',
            'documents_approved',
            'contract_signed',
            'cashflows_switch_notice_sent',
            'contract_submitted',
            'application_approved',
            'approval_email_sent',
            'gateway_contract_sent',
            'gateway_contract_signed',
            'gateway_details_received',
            'wordpress_credentials_collected',
            'invoice_sent',
            'invoice_paid',
            'gateway_integrated',
            'account_live'
        ) DEFAULT 'created'");
    }

    public function down(): void
    {
        DB::table('application_statuses')
            ->where('current_step', 'cashflows_switch_notice_sent')
            ->update(['current_step' => 'contract_signed']);

        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'contract_sent',
            'documents_uploaded',
            'documents_approved',
            'contract_signed',
            'contract_submitted',
            'application_approved',
            'approval_email_sent',
            'gateway_contract_sent',
            'gateway_contract_signed',
            'gateway_details_received',
            'wordpress_credentials_collected',
            'invoice_sent',
            'invoice_paid',
            'gateway_integrated',
            'account_live'
        ) DEFAULT 'created'");
    }
};
