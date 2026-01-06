<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->timestamp('dumped_at')->nullable()->after('completed_at');
            $table->text('dumped_reason')->nullable()->after('dumped_at');
        });
    }

    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropColumn(['dumped_at', 'dumped_reason']);
        });
    }
};