<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_name',
        'account_id',
        'application_id',
        'user_id',
        'status',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}