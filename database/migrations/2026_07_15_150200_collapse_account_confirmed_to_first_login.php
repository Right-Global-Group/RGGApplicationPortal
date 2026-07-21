<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One fact, one name.
     *
     * `status` was written on every account and read by nothing - isConfirmed() answered
     * from first_login_at, not from the column - so it was a second, drifting name for a
     * fact first_login_at already stated. It joins the dead `owner` column.
     *
     * `credentials_sent_at` is kept but renamed. There are no credentials to send any
     * more, so under its old name its meaning would have drifted exactly as "confirmed"
     * did. It is still read by the account page, and paired with first_login_at it shows
     * mailed-but-never-clicked, which is the signature of the very lockout this feature
     * exists to fix - so it earns its keep, under a name that says what it holds.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('credentials_sent_at', 'link_sent_at');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('link_sent_at', 'credentials_sent_at');
            $table->unsignedTinyInteger('status')->default(0)->after('mobile');
        });
    }
};
