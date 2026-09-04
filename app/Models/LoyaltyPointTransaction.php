<?php

namespace App\Models;

use App\Enums\LoyaltyTransactionSourceType;
use App\Enums\LoyaltyTransactionType;
use Database\Factories\LoyaltyPointTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPointTransaction extends AbstractModel
{
    /** @use HasFactory<LoyaltyPointTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'loyalty_account_id',
        'customer_id',
        'type',
        'points',
        'source_type',
        'source_id',
        'idempotency_key',
        'reason_code',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'loyalty_account_id' => 'integer',
            'customer_id' => 'integer',
            'type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'source_type' => LoyaltyTransactionSourceType::class,
            'source_id' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
