<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate every non-empty legacy one-shot note into the application's
        // thread as its first merchant message. Best-effort timestamp: the
        // status row's updated_at (when the note was last written).
        $legacyNotes = DB::table('application_statuses')
            ->join('applications', 'applications.id', '=', 'application_statuses.application_id')
            // Guard: a message must have an author; skip applications with no account
            ->whereNotNull('applications.account_id')
            ->whereNotNull('application_statuses.account_message_notes')
            ->where('application_statuses.account_message_notes', '!=', '')
            ->select(
                'application_statuses.application_id',
                'applications.account_id',
                'application_statuses.account_message_notes',
                'application_statuses.current_step',
                'application_statuses.updated_at'
            )
            ->get();

        foreach ($legacyNotes as $note) {
            DB::table('application_messages')->insert([
                'application_id' => $note->application_id,
                'user_id' => null,
                'account_id' => $note->account_id,
                'body' => $note->account_message_notes,
                'is_internal' => false,
                'current_step' => $note->current_step,
                'created_at' => $note->updated_at ?? now(),
                'updated_at' => $note->updated_at ?? now(),
            ]);
        }

        // The account_message_to_user reminder flow is removed with the legacy
        // modal; deactivate any still-pending reminders so the scheduler never
        // hits its (now deleted) email type.
        DB::table('email_reminders')
            ->where('email_type', 'account_message_to_user')
            ->update(['is_active' => false]);

        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn('account_message_notes');
        });
    }

    public function down(): void
    {
        // Data restoration is not attempted; the migrated messages remain in
        // the thread and reminders stay deactivated.
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->text('account_message_notes')->nullable()->after('additional_info_notes');
        });
    }
};
