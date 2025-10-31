<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * New flow:
     * created -> contract_sent -> documents_uploaded -> documents_approved -> 
     * contract_signed -> contract_submitted -> application_approved -> 
     * invoice_sent -> invoice_paid -> gateway_integrated -> account_live
     */
    public function up(): void
    {
        // STEP 1: Expand the ENUM to include BOTH old and new values temporarily
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
            'account_live',
            'application_sent',
            'contract_completed'
        ) DEFAULT 'created'");

        // STEP 2: Now migrate the data from old values to new values
        DB::table('application_statuses')
            ->where('current_step', 'application_sent')
            ->update(['current_step' => 'contract_sent']);
            
        DB::table('application_statuses')
            ->where('current_step', 'contract_completed')
            ->update(['current_step' => 'contract_signed']);

        // STEP 3: Remove the old values from the ENUM
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

        // STEP 4: Add new columns for DocuSign tracking (only if they don't exist)
        Schema::table('application_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('application_statuses', 'docusign_recipient_status')) {
                $table->json('docusign_recipient_status')->nullable();
            }
            if (!Schema::hasColumn('application_statuses', 'contract_viewed_at')) {
                $table->timestamp('contract_viewed_at')->nullable();
            }
            if (!Schema::hasColumn('application_statuses', 'contract_signed_at')) {
                $table->timestamp('contract_signed_at')->nullable();
            }
        });

        // STEP 5: Add recipient_status column to application_documents if not exists
        if (!Schema::hasColumn('application_documents', 'recipient_status')) {
            Schema::table('application_documents', function (Blueprint $table) {
                $table->json('recipient_status')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Expand ENUM to include old values
        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'documents_uploaded',
            'documents_approved',
            'application_sent',
            'contract_completed',
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
            'account_live',
            'contract_sent',
            'contract_signed'
        ) DEFAULT 'created'");

        // Migrate data back
        DB::table('application_statuses')
            ->where('current_step', 'contract_sent')
            ->update(['current_step' => 'application_sent']);
            
        DB::table('application_statuses')
            ->where('current_step', 'contract_signed')
            ->update(['current_step' => 'contract_completed']);

        // Remove new values from ENUM
        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'documents_uploaded',
            'documents_approved',
            'application_sent',
            'contract_completed',
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

        Schema::table('application_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('application_statuses', 'docusign_recipient_status')) {
                $table->dropColumn('docusign_recipient_status');
            }
            if (Schema::hasColumn('application_statuses', 'contract_viewed_at')) {
                $table->dropColumn('contract_viewed_at');
            }
            if (Schema::hasColumn('application_statuses', 'contract_signed_at')) {
                $table->dropColumn('contract_signed_at');
            }
        });

        if (Schema::hasColumn('application_documents', 'recipient_status')) {
            Schema::table('application_documents', function (Blueprint $table) {
                $table->dropColumn('recipient_status');
            });
        }
    }
};