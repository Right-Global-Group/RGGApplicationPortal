<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merchants sign in with a signed link, so there is no merchant credential. Dropping
     * the column is what makes the original bug impossible rather than merely fixed:
     * nothing can silently rotate a password that does not exist.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('password')->nullable()->after('mobile');
        });
    }
};
