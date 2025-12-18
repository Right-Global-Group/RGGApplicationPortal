<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add additional document fields to applications table
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('requires_additional_document')->default(false)->after('setup_fee');
            $table->string('additional_document_name')->nullable()->after('requires_additional_document');
            $table->text('additional_document_instructions')->nullable()->after('additional_document_name');
        });

        // Update application_statuses enum to include documents_approved
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

        // Add documents_approved_at timestamp
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->timestamp('documents_approved_at')->nullable()->after('documents_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['requires_additional_document', 'additional_document_name', 'additional_document_instructions']);
        });

        DB::statement("ALTER TABLE application_statuses MODIFY COLUMN current_step ENUM(
            'created',
            'documents_uploaded',
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
            $table->dropColumn('documents_approved_at');
        });
    }
};