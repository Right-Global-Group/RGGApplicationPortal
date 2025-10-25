<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'documents_uploaded' step (after fees_confirmed)
        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'fees_confirmed',
            'documents_uploaded',
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'documents_uploaded' from enum
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
    }
};