<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('emailable'); // polymorphic relation (Account or Application)
            $table->string('email_type'); // 'account_credentials', 'application_created', etc.
            $table->string('recipient_email');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->timestamp('sent_at');
            $table->boolean('opened')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};