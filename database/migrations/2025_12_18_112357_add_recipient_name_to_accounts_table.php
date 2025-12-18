<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('recipient_name')->nullable()->after('name');
        });
    }
    
    public function down()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('recipient_name');
        });
    }
};
