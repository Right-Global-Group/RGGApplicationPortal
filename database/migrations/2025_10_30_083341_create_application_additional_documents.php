<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create a new table to track multiple additional document requests
        Schema::create('application_additional_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->string('document_name');
            $table->text('instructions')->nullable();
            $table->boolean('is_uploaded')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('requested_at');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });

        // Remove old single-document fields from applications table
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['requires_additional_document', 'additional_document_name', 'additional_document_instructions']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_additional_documents');
        
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('requires_additional_document')->default(false);
            $table->string('additional_document_name')->nullable();
            $table->text('additional_document_instructions')->nullable();
        });
    }
};