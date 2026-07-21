<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            // sent_at records handoff to the mailer, which says nothing about whether the
            // send ultimately worked. A failure needs somewhere to land that staff can
            // find next to the sends, rather than only in a log nobody reads.
            $table->timestamp('failed_at')->nullable()->after('sent_at');
            $table->text('error')->nullable()->after('failed_at');

            // A failure is recorded from the mailable, which knows the recipient but not
            // what the mail was about.
            $table->timestamp('sent_at')->nullable()->change();
            $table->string('emailable_type')->nullable()->change();
            $table->unsignedBigInteger('emailable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn(['failed_at', 'error']);
        });
    }
};
