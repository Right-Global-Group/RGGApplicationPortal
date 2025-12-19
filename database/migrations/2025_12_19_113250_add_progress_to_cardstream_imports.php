<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('cardstream_imports', function (Blueprint $table) {
            $table->integer('processed_rows')->default(0)->after('total_rows');
            $table->integer('estimated_total')->nullable()->after('processed_rows');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cardstream_imports', function (Blueprint $table) {
            //
        });
    }
};
