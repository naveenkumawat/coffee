<?php

namespace App\Models;

use App\Enums\ReferralStatus;
use Database\Factories\CustomerReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerReferral extends AbstractModel
{
    /** @use HasFactory<CustomerReferralFactory> */
    use HasFactory;

    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referral_code_snapshot',
        'status',
        'qualified_order_id',
        'qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReferralStatus::class,
            'qualified_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function qualifiedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'qualified_order_id');
    }

    public function reward(): HasOne
    {
        return $this->hasOne(CustomerReward::class, 'source_referral_id');
    }
}
