<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('email')->after('name');
            $table->string('password')->after('email');
            $table->tinyInteger('status')->default(0)->after('password')->comment('0=pending, 1=confirmed');
            $table->timestamp('credentials_sent_at')->nullable()->after('status');
            $table->timestamp('first_login_at')->nullable()->after('credentials_sent_at');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['email', 'password', 'status', 'credentials_sent_at', 'first_login_at', 'remember_token']);
        });
    }
};