<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardstream_transaction_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('cardstream_imports')->onDelete('cascade');
            $table->string('merchant_id');
            $table->string('merchant_name');
            $table->integer('total_transactions')->default(0);
            $table->integer('accepted')->default(0);
            $table->integer('received')->default(0);
            $table->integer('declined')->default(0);
            $table->integer('canceled')->default(0);
            $table->timestamps();
            
            $table->index(['import_id', 'merchant_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardstream_transaction_summaries');
    }
};