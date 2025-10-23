<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_reminders', function (Blueprint $table) {
            $table->id();
            $table->morphs('remindable'); // polymorphic relation (Account or Application)
            $table->string('email_type'); // 'account_credentials', 'application_created'
            $table->string('interval'); // '1_day', '3_days', '1_week', '2_weeks', '1_month'
            $table->timestamp('next_send_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_reminders');
    }
};