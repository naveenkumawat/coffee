<?php

namespace App\Models;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use Database\Factories\LoyaltyRewardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class LoyaltyReward extends AbstractModel
{
    /** @use HasFactory<LoyaltyRewardFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'status',
        'reward_type',
        'points_cost',
        'config',
        'minimum_spend',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_limit_per_customer',
        'usage_limit_per_customer_period_days',
        'priority',
        'customer_description',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoyaltyRewardStatus::class,
            'reward_type' => LoyaltyRewardType::class,
            'points_cost' => 'integer',
            'config' => 'array',
            'minimum_spend' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'usage_limit_per_customer_period_days' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'loyalty_reward_product');
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'loyalty_reward_product_category');
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'loyalty_reward_add_on');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'loyalty_reward_id');
    }

    public function displayDescription(): string
    {
        return filled($this->customer_description)
            ? (string) $this->customer_description
            : (string) $this->name;
    }

    public function isScheduledActive(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function isRedeemable(?Carbon $at = null): bool
    {
        $status = $this->status instanceof LoyaltyRewardStatus
            ? $this->status
            : LoyaltyRewardStatus::tryFrom((string) $this->status);

        return $status === LoyaltyRewardStatus::Active && $this->isScheduledActive($at);
    }
}
