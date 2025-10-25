<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_application_id')->nullable()->after('user_id');
            $table->foreign('parent_application_id')->references('id')->on('applications')->onDelete('set null');
            $table->index('parent_application_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['parent_application_id']);
            $table->dropIndex(['parent_application_id']);
            $table->dropColumn('parent_application_id');
        });
    }
};