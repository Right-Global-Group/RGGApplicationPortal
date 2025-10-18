<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->enum('current_step', [
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
            ])->default('created');
            $table->json('step_history')->nullable(); // Track all step transitions
            $table->timestamp('contract_sent_at')->nullable();
            $table->timestamp('contract_completed_at')->nullable();
            $table->timestamp('contract_submitted_at')->nullable();
            $table->timestamp('application_approved_at')->nullable();
            $table->timestamp('invoice_sent_at')->nullable();
            $table->timestamp('invoice_paid_at')->nullable();
            $table->timestamp('gateway_integrated_at')->nullable();
            $table->timestamp('account_live_at')->nullable();
            $table->string('docusign_envelope_id')->nullable();
            $table->string('docusign_status')->nullable();
            $table->boolean('requires_additional_info')->default(false);
            $table->text('additional_info_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('application_statuses');
    }
};
