<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'emailable_type',
        'emailable_id',
        'email_type',
        'recipient_email',
        'subject',
        'body',
        'sent_at',
        'opened',
        'opened_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened' => 'boolean',
        'opened_at' => 'datetime',
    ];

    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markAsOpened(): void
    {
        if (!$this->opened) {
            $this->update([
                'opened' => true,
                'opened_at' => now(),
            ]);
        }
    }
}