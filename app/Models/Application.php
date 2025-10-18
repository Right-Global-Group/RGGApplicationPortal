<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'account_id',
        'email',
        'phone',
        'address',
        'city',
        'region',
        'country',
        'postal_code',
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
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
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

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('trading_name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%');
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
}