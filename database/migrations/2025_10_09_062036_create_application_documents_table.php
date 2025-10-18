<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->enum('document_type', ['contract', 'pci_form', 'invoice', 'other']);
            $table->string('file_path')->nullable();
            $table->string('external_id')->nullable(); // DocuSign envelope ID, etc.
            $table->string('external_system')->nullable(); // 'docusign', 'local', etc.
            $table->enum('status', ['pending', 'sent', 'viewed', 'completed', 'declined'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('application_documents');
    }
};
