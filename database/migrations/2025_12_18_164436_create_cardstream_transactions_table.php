<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardstream_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('cardstream_imports')->onDelete('cascade');
            $table->string('transaction_id')->unique();
            $table->timestamp('transaction_date');
            $table->string('merchant_id');
            $table->string('merchant_name');
            $table->string('action'); // SALE, REFUND, etc.
            $table->string('currency', 3);
            $table->decimal('amount', 10, 2);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('card_type')->nullable();
            $table->string('response_code');
            $table->string('response_message');
            $table->string('state'); // accepted, declined, received, canceled
            $table->text('raw_data')->nullable(); // Store full row as JSON
            $table->timestamps();
            
            $table->index('merchant_name');
            $table->index('state');
            $table->index('import_id');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardstream_transactions');
    }
};