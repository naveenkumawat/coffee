<?php

namespace App\Models;

use App\Enums\CustomerRewardType;
use App\Enums\PromotionDiscountType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRewardRedemption extends AbstractModel
{
    protected $fillable = [
        'order_id',
        'customer_reward_id',
        'reward_type',
        'source_referral_id',
        'description_snapshot',
        'benefit_amount',
        'original_amount',
        'preserved_taxable_amount',
        'product_id',
        'variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'quantity',
        'coupon_code_snapshot',
        'discount_type_snapshot',
        'discount_value_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => CustomerRewardType::class,
            'discount_type_snapshot' => PromotionDiscountType::class,
            'benefit_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'preserved_taxable_amount' => 'decimal:2',
            'discount_value_snapshot' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(CustomerReward::class, 'customer_reward_id');
    }
}
