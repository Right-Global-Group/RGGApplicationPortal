<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAdditionalDocument extends Model
{
    protected $fillable = [
        'application_id',
        'document_name',
        'instructions',
        'is_uploaded',
        'notes',
        'requested_by',
        'requested_at',
        'uploaded_at',
    ];

    protected $casts = [
        'is_uploaded' => 'boolean',
        'requested_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}