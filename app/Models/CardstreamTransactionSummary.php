<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardstreamTransactionSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'transaction_id',
        'transaction_date',
        'merchant_id',
        'merchant_name',
        'action',
        'currency',
        'amount',
        'customer_name',
        'customer_email',
        'card_type',
        'response_code',
        'response_message',
        'state',
        'raw_data',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2',
        'raw_data' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(CardstreamImport::class, 'import_id');
    }

    /**
     * Determine transaction state from response
     */
    public static function determineState(string $responseMessage, string $responseCode): string
    {
        $responseMessage = strtolower($responseMessage);
        $responseCode = strtolower($responseCode);
        
        if (in_array($responseMessage, ['authorised', 'authorized']) || $responseCode === 'accepted') {
            return 'accepted';
        }
        
        if (str_contains($responseMessage, 'decline') || str_contains($responseCode, 'decline')) {
            return 'declined';
        }
        
        if (str_contains($responseMessage, 'cancel') || str_contains($responseCode, 'cancel')) {
            return 'canceled';
        }
        
        return 'received';
    }
}