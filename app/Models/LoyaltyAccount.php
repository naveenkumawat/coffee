<?php

namespace App\Models;

use Database\Factories\LoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyAccount extends AbstractModel
{
    /** @use HasFactory<LoyaltyAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'available_points',
        'lifetime_earned_points',
        'lifetime_redeemed_points',
        'lifetime_adjusted_points',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'available_points' => 'integer',
            'lifetime_earned_points' => 'integer',
            'lifetime_redeemed_points' => 'integer',
            'lifetime_adjusted_points' => 'integer',
            'version' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }
}
