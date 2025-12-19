<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardstreamMerchantTransactionList extends Model
{
    protected $fillable = [
        'import_id',
        'merchant_id',
        'merchant_name',
        'total_transactions',
        'accepted',
        'received',
        'declined',
        'canceled',
    ];

    protected $casts = [
        'total_transactions' => 'integer',
        'accepted' => 'integer',
        'received' => 'integer',
        'declined' => 'integer',
        'canceled' => 'integer',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(CardstreamImport::class, 'import_id');
    }
}