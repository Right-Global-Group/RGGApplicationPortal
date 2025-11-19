<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'account_id',
        'user_id',
        'parent_application_id',
        'setup_fee',
	    'scaling_fee_start_month',
        'transaction_percentage',
        'transaction_fixed_fee',
        'monthly_fee',
        'monthly_minimum',
        'service_fee',
        'trading_name',
        'company_number',
        'business_type',
        'trading_start_date',
        'estimated_annual_turnover',
        'website_url',
        'pci_compliant',
        'gateway_preference',
        // New gateway partner fields
        'gateway_partner',
        'gateway_mid',
        'gateway_integration_details',
        // New WordPress fields
        'wordpress_url',
        'wordpress_admin_email',
        'wordpress_admin_username',
        'requires_additional_document',
        'additional_document_name',
        'additional_document_instructions',
        'cardstream_username',
        'cardstream_password',
        'cardstream_merchant_id',
        'cardstream_credentials_entered_at',
        'wordpress_url',
        'wordpress_username',
        'wordpress_password',
        'wordpress_credentials_entered_at',
    ];

    protected $casts = [
        'trading_start_date' => 'date',
        'estimated_annual_turnover' => 'decimal:2',
        'pci_compliant' => 'boolean',
        'setup_fee' => 'decimal:2',
        'transaction_percentage' => 'decimal:2',
        'transaction_fixed_fee' => 'decimal:2',
        'monthly_fee' => 'decimal:2',
        'monthly_minimum' => 'decimal:2',
'scaling_fee_start_month' => 'integer',
        'service_fee' => 'decimal:2',
        'gateway_integration_details' => 'array',
        'requires_additional_document' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function parentApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'parent_application_id');
    }

    public function childApplications(): HasMany
    {
        return $this->hasMany(Application::class, 'parent_application_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function status(): HasOne
    {
        return $this->hasOne(ApplicationStatus::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function gatewayIntegration(): HasOne
    {
        return $this->hasOne(GatewayIntegration::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function emailReminders()
    {
        return $this->morphMany(EmailReminder::class, 'remindable');
    }

    public function emailLogs()
    {
        return $this->morphMany(EmailLog::class, 'emailable');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('trading_name', 'like', '%'.$search.'%');
        })->when($filters['status'] ?? null, function ($query, $status) {
            $query->whereHas('status', function ($q) use ($status) {
                $q->where('current_step', $status);
            });
        })->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }

    protected static function booted()
    {
        static::created(function ($application) {
            // Automatically create status record when application is created
            $application->status()->create([
                'current_step' => 'created',
            ]);
        });
    }

    // Gateway Partner Helper Methods
    public function getGatewayPartnerNameAttribute(): ?string
    {
        if (!$this->gateway_partner) {
            return null;
        }

        return config("gateway-partners.{$this->gateway_partner}.name");
    }

    public function getGatewayPartnerEmailAttribute(): ?string
    {
        if (!$this->gateway_partner) {
            return null;
        }

        return config("gateway-partners.{$this->gateway_partner}.contact_email");
    }

    public function hasGatewayDetails(): bool
    {
        return !empty($this->gateway_mid) 
            && !empty($this->gateway_integration_details);
    }

    // WordPress Helper Methods
    public function hasWordPressCredentials(): bool
    {
        return !empty($this->wordpress_url) 
            && !empty($this->wordpress_admin_email) 
            && !empty($this->wordpress_admin_username);
    }

    public function additionalDocuments(): HasMany
    {
        return $this->hasMany(ApplicationAdditionalDocument::class);
    }

    /**
     * Get all pending additional document requests
     */
    public function pendingAdditionalDocuments()
    {
        return $this->additionalDocuments()->where('is_uploaded', false);
    }

    /**
     * Check if all additional documents have been uploaded
     */
    public function hasAllAdditionalDocumentsUploaded(): bool
    {
        return $this->additionalDocuments()->where('is_uploaded', false)->count() === 0;
    }
}