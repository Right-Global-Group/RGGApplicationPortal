<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gateway_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->enum('gateway_provider', ['cardstream', 'acquired', 'other']);
            $table->string('merchant_id')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'testing', 'live', 'failed'])->default('pending');
            $table->timestamp('integration_started_at')->nullable();
            $table->timestamp('testing_completed_at')->nullable();
            $table->timestamp('went_live_at')->nullable();
            $table->text('integration_notes')->nullable();
            $table->json('api_credentials')->nullable(); // Encrypted
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gateway_integrations');
    }
};
