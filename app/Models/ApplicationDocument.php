<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    // Document categories based on KYC requirements
    const CATEGORY_PHOTOGRAPHIC_ID = 'photographic_id';
    const CATEGORY_PROOF_OF_ADDRESS = 'proof_of_address';
    const CATEGORY_COMPANIES_HOUSE = 'companies_house_certificate';
    const CATEGORY_BANK_STATEMENT = 'business_bank_statement';
    const CATEGORY_ADDITIONAL_REQUESTED = 'additional_requested';

    protected $fillable = [
        'application_id',
        'document_type',
        'document_category',
        'file_path',
        'original_filename',
        'uploaded_by',
        'uploaded_by_type',
        'external_id',
        'external_system',
        'status',
        'sent_at',
        'completed_at',
        'dumped_at',
        'dumped_reason',
        'is_library_uploaded', // flag for docs uploaded from library
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'dumped_at' => 'datetime',
        'is_library_uploaded' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function uploader()
    {
        if ($this->uploaded_by_type === 'user') {
            return $this->belongsTo(User::class, 'uploaded_by');
        } elseif ($this->uploaded_by_type === 'account') {
            return $this->belongsTo(Account::class, 'uploaded_by');
        }
        return null;
    }

    /**
     * Get all required document categories (base categories only)
     */
    public static function getRequiredCategories(): array
    {
        return [
            self::CATEGORY_PHOTOGRAPHIC_ID => 'Photographic ID',
            self::CATEGORY_PROOF_OF_ADDRESS => 'Proof of Address',
            self::CATEGORY_COMPANIES_HOUSE => 'Companies House Certificate',
            self::CATEGORY_BANK_STATEMENT => 'Business Bank Statement',
        ];
    }

    /**
     * Get uploadable categories for an application (required + additional requested)
     */
    public static function getUploadableCategories(Application $application): array
    {
        $categories = self::getRequiredCategories();
        
        // Add all pending additional documents
        $additionalDocs = $application->additionalDocuments()->where('is_uploaded', false)->get();
        foreach ($additionalDocs as $doc) {
            $categories["additional_requested_{$doc->id}"] = $doc->document_name;
        }
        
        return $categories;
    }

    /**
     * Get extra (library-uploaded) categories for an application
     * These are categories that don't match required or additional requested
     */
    public static function getExtraCategories(Application $application): array
    {
        $uploadableCategories = array_keys(self::getUploadableCategories($application));
        
        // Get all unique categories from uploaded documents
        $allUploadedCategories = $application->documents()
            ->select('document_category')
            ->distinct()
            ->pluck('document_category')
            ->filter()
            ->toArray();
        
        // Find categories that are not in uploadable list
        $extraCategories = [];
        foreach ($allUploadedCategories as $category) {
            if (!in_array($category, $uploadableCategories)) {
                $extraCategories[$category] = ucwords(str_replace('_', ' ', $category));
            }
        }
        
        return $extraCategories;
    }

    /**
     * Get category display name
     */
    public function getCategoryNameAttribute(): string
    {
        // Check if this is an additional document
        if ($this->document_category === self::CATEGORY_ADDITIONAL_REQUESTED && $this->application) {
            return $this->application->additional_document_name ?: 'Additional Requested Document';
        }
        
        $categories = self::getRequiredCategories();
        return $categories[$this->document_category] ?? ucwords(str_replace('_', ' ', $this->document_category));
    }

    /**
     * Get category description
     */
    public static function getCategoryDescription(string $category): string
    {
        $descriptions = [
            self::CATEGORY_PHOTOGRAPHIC_ID => 'For anyone with 25% or more shares',
            self::CATEGORY_PROOF_OF_ADDRESS => 'Utility, government or bank statement dated in the last 3 months',
            self::CATEGORY_COMPANIES_HOUSE => 'Official Companies House certificate',
            self::CATEGORY_BANK_STATEMENT => 'Business bank statement',
            self::CATEGORY_ADDITIONAL_REQUESTED => 'Additional document requested by administrator',
        ];

        return $descriptions[$category] ?? '';
    }

    /**
     * Get category description for a specific application (includes custom instructions)
     */
    public static function getCategoryDescriptionForApplication(string $category, Application $application): string
    {
        // Check if this is an additional document category
        if (str_starts_with($category, 'additional_requested_')) {
            $docId = str_replace('additional_requested_', '', $category);
            $additionalDoc = $application->additionalDocuments()->find($docId);
            
            if ($additionalDoc) {
                return $additionalDoc->instructions ?: 'Additional document requested by administrator';
            }
        }
        
        return self::getCategoryDescription($category);
    }

    /**
     * Get all valid categories for validation (includes additional_requested)
     */
    public static function getAllValidCategoriesForApplication(Application $application): array
    {
        $baseCategories = array_keys(self::getRequiredCategories());
        
        // Add additional document categories
        $additionalDocs = $application->additionalDocuments()->get();
        foreach ($additionalDocs as $doc) {
            $baseCategories[] = "additional_requested_{$doc->id}";
        }
        
        return $baseCategories;
    }

    /**
     * Check if a category is uploadable (required or additional requested)
     */
    public static function isUploadableCategory(string $category, Application $application): bool
    {
        $uploadableCategories = array_keys(self::getUploadableCategories($application));
        return in_array($category, $uploadableCategories);
    }

    public function isDumped(): bool
    {
        return !is_null($this->dumped_at);
    }

    /**
     * Get the parent document (if this is an edited version)
     */
    public function parentDocument()
    {
        return $this->belongsTo(ApplicationDocument::class, 'parent_document_id');
    }

    /**
     * Get the document that superseded this one
     */
    public function supersededBy()
    {
        return $this->belongsTo(ApplicationDocument::class, 'superseded_by_id');
    }

    /**
     * Get all versions of this document (if this is the original)
     */
    public function versions()
    {
        return $this->hasMany(ApplicationDocument::class, 'parent_document_id');
    }

    /**
     * Check if this document is superseded
     */
    public function isSuperseded(): bool
    {
        return $this->is_superseded;
    }

    /**
     * Get the most recent version of this document category
     */
    public static function getLatestVersion(int $applicationId, string $category)
    {
        return self::where('application_id', $applicationId)
            ->where('document_category', $category)
            ->where('is_superseded', false)
            ->latest('created_at')
            ->first();
    }
}