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
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
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
     * Get all required document categories
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
     * Get category display name
     */
    public function getCategoryNameAttribute(): string
    {
        $categories = self::getRequiredCategories();
        return $categories[$this->document_category] ?? $this->document_category;
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
        ];

        return $descriptions[$category] ?? '';
    }
}