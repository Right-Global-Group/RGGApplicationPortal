<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayIntegration extends Model
{
    protected $fillable = [
        'application_id',
        'gateway_provider',
        'merchant_id',
        'status',
        'integration_started_at',
        'testing_completed_at',
        'went_live_at',
        'integration_notes',
        'api_credentials',
    ];

    protected $casts = [
        'integration_started_at' => 'datetime',
        'testing_completed_at' => 'datetime',
        'went_live_at' => 'datetime',
        'api_credentials' => 'encrypted:array',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}