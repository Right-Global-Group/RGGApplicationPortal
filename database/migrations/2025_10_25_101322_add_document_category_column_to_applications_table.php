<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            // Document category for organizing uploads
            $table->string('document_category')->nullable()->after('document_type');
            
            // Original filename for display purposes
            $table->string('original_filename')->nullable()->after('file_path');
            
            // Track who uploaded the document
            $table->unsignedBigInteger('uploaded_by')->nullable()->after('application_id');
            $table->string('uploaded_by_type')->nullable()->after('uploaded_by'); // 'user' or 'account'
            
            // Index for faster queries by category
            $table->index('document_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex(['document_category']);
            $table->dropColumn([
                'document_category',
                'original_filename',
                'uploaded_by',
                'uploaded_by_type',
            ]);
        });
    }
};