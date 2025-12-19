<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cardstream_imports', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('total_rows');
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cardstream_imports', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message']);
        });
    }
};