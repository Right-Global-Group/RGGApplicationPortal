<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardstreamImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'total_rows',
        'processed_rows',
        'estimated_total',
        'status',
        'error_message',
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'estimated_total' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function merchantTransactions(): HasMany
    {
        return $this->hasMany(CardstreamMerchantTransactionList::class, 'import_id');
    }

    /**
     * Get merchant statistics (now from dedicated table)
     */
    public function getMerchantStats()
    {
        return $this->merchantTransactions()
            ->orderBy('merchant_name')
            ->get();
    }
}