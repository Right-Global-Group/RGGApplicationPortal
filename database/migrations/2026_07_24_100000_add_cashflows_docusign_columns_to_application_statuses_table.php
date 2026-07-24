<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Own columns for the Cashflows switch-notice envelope, separate from the main
     * contract's docusign_envelope_id/docusign_status/docusign_recipient_status -
     * reusing those would collide with the main contract's tracking on the same
     * application. (The gateway-partner flow tried to reuse a column this way and
     * that column was never actually migrated - a lesson not to repeat here.)
     */
    public function up(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->string('cashflows_docusign_envelope_id')->nullable()->after('cashflows_switch_notice_sent_at');
            $table->string('cashflows_docusign_status')->nullable()->after('cashflows_docusign_envelope_id');
            $table->json('cashflows_docusign_recipient_status')->nullable()->after('cashflows_docusign_status');
        });
    }

    public function down(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn([
                'cashflows_docusign_envelope_id',
                'cashflows_docusign_status',
                'cashflows_docusign_recipient_status',
            ]);
        });
    }
};
