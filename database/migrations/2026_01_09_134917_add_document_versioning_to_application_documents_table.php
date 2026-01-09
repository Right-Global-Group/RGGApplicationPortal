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
            // Track document versions and edits
            $table->unsignedBigInteger('parent_document_id')->nullable()->after('external_system');
            $table->boolean('is_superseded')->default(false)->after('is_library_uploaded');
            $table->unsignedBigInteger('superseded_by_id')->nullable()->after('is_superseded');
            $table->timestamp('superseded_at')->nullable()->after('superseded_by_id');
            
            // Add foreign keys
            $table->foreign('parent_document_id')
                ->references('id')
                ->on('application_documents')
                ->onDelete('set null');
                
            $table->foreign('superseded_by_id')
                ->references('id')
                ->on('application_documents')
                ->onDelete('set null');
            
            // Add index for querying latest versions (with custom short name)
            $table->index(['application_id', 'document_category', 'is_superseded'], 'app_docs_latest_version_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropForeign(['parent_document_id']);
            $table->dropForeign(['superseded_by_id']);
            $table->dropIndex('app_docs_latest_version_idx');
            $table->dropColumn([
                'parent_document_id',
                'is_superseded',
                'superseded_by_id',
                'superseded_at',
            ]);
        });
    }
};