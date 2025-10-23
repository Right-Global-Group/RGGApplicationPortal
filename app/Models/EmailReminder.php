<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailReminder extends Model
{
    protected $fillable = [
        'remindable_type',
        'remindable_id',
        'email_type',
        'interval',
        'next_send_at',
        'is_active',
    ];

    protected $casts = [
        'next_send_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function updateNextSendDate(): void
    {
        $intervals = [
            '1_day' => now()->addDay(),
            '3_days' => now()->addDays(3),
            '1_week' => now()->addWeek(),
            '2_weeks' => now()->addWeeks(2),
            '1_month' => now()->addMonth(),
        ];

        $this->update([
            'next_send_at' => $intervals[$this->interval] ?? now()->addDay(),
        ]);
    }
}