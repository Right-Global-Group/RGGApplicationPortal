<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->text('account_message_notes')->nullable()->after('additional_info_notes');
        });
    }

    public function down(): void
    {
        Schema::table('application_statuses', function (Blueprint $table) {
            $table->dropColumn('account_message_notes');
        });
    }
};