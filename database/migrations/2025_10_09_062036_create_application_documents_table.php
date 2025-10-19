<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->string('document_type'); // contract, invoice, etc.
            $table->string('file_path')->nullable(); // local storage path
            $table->string('external_id')->nullable(); // DocuSign envelope ID
            $table->string('external_system')->nullable(); // docusign, etc.
            $table->string('status')->default('pending'); // pending, sent, viewed, completed, declined
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'document_type']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};