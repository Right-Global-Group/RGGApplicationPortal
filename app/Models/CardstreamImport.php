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
        'imported_at',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CardstreamTransaction::class, 'import_id');
    }

    /**
     * Get aggregated merchant statistics for this import
     */
    public function getMerchantStats()
    {
        return $this->transactions()
            ->selectRaw('
                merchant_name,
                merchant_id,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN state = "accepted" THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN state = "received" THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN state = "declined" THEN 1 ELSE 0 END) as declined,
                SUM(CASE WHEN state = "canceled" THEN 1 ELSE 0 END) as canceled
            ')
            ->groupBy('merchant_name', 'merchant_id')
            ->orderBy('merchant_name')
            ->get();
    }
}