<?php

namespace App\Models;

use App\Events\FeesConfirmedEvent;
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
        'transaction_percentage',
        'transaction_fixed_fee',
        'monthly_fee',
        'monthly_minimum',
        'service_fee',
        'fees_confirmed',
        'fees_confirmed_at',
        'trading_name',
        'company_number',
        'business_type',
        'trading_start_date',
        'estimated_annual_turnover',
        'website_url',
        'pci_compliant',
        'gateway_preference',
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
        'service_fee' => 'decimal:2',
        'fees_confirmed' => 'boolean',
        'fees_confirmed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function account()
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

    public function confirmFees(): void
    {
        $this->update([
            'fees_confirmed' => true,
            'fees_confirmed_at' => now(),
        ]);
        
        // Transition status to fees_confirmed
        if ($this->status) {
            $this->status->transitionTo('fees_confirmed', 'Fees confirmed by account');
        }

        // Fire event to notify admin/user
        event(new FeesConfirmedEvent($this));
    }
}