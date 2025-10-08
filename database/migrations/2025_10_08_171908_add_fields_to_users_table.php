<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('first_name', 25);
            $table->string('last_name', 25);
            $table->string('photo_path', 100)->nullable();
            $table->boolean('owner')->default(false);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropColumn(['account_id', 'first_name', 'last_name', 'photo_path', 'owner']);
            $table->dropSoftDeletes();
        });
    }
};