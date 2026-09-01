<?php

namespace App\Models;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\PromotionDiscountType;
use Carbon\CarbonInterface;
use Database\Factories\CustomerRewardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReward extends AbstractModel
{
    /** @use HasFactory<CustomerRewardFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_referral_id',
        'reward_type',
        'status',
        'earned_at',
        'expires_at',
        'redeemed_order_id',
        'redeemed_at',
        'product_id',
        'variant_id',
        'product_name_snapshot',
        'variant_name_snapshot',
        'quantity',
        'coupon_code',
        'discount_type',
        'discount_value',
        'maximum_discount_amount',
        'minimum_subtotal',
    ];

    protected function casts(): array
    {
        return [
            'reward_type' => CustomerRewardType::class,
            'status' => CustomerRewardStatus::class,
            'discount_type' => PromotionDiscountType::class,
            'earned_at' => 'datetime',
            'expires_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'quantity' => 'integer',
            'discount_value' => 'decimal:2',
            'maximum_discount_amount' => 'decimal:2',
            'minimum_subtotal' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(CustomerReferral::class, 'source_referral_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function redeemedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'redeemed_order_id');
    }

    public function isUsableAt(CarbonInterface $at): bool
    {
        if ($this->status !== CustomerRewardStatus::Available && $this->status !== CustomerRewardStatus::Reserved) {
            return false;
        }

        if ($this->expires_at !== null && $at->greaterThan($this->expires_at)) {
            return false;
        }

        return $this->status === CustomerRewardStatus::Available
            || $this->status === CustomerRewardStatus::Reserved;
    }

    public function isExpiredAt(CarbonInterface $at): bool
    {
        return $this->expires_at !== null && $at->greaterThan($this->expires_at);
    }

    /**
     * @param  Builder<CustomerReward>  $query
     * @return Builder<CustomerReward>
     */
    public function scopeActiveForCustomer(Builder $query, int $userId, CarbonInterface $at): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('status', CustomerRewardStatus::Available->value)
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $at);
            });
    }

    public function displayTitle(): string
    {
        if ($this->reward_type === CustomerRewardType::FreeDrink) {
            $label = (string) ($this->product_name_snapshot ?: 'Free Drink');
            if (filled($this->variant_name_snapshot)) {
                $label .= ' · '.$this->variant_name_snapshot;
            }

            return $label;
        }

        if ($this->discount_type === PromotionDiscountType::Percentage) {
            return rtrim(rtrim((string) $this->discount_value, '0'), '.').'% OFF';
        }

        return '₹'.number_format((float) $this->discount_value, 2).' OFF';
    }
}
