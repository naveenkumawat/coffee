<?php

namespace App\Models;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends AbstractModel
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'minimum_subtotal',
        'maximum_discount_amount',
        'usage_limit',
        'usage_limit_per_customer',
        'priority',
        'is_active',
        'stackable',
        'applies_to_all_products',
        'applies_to_all_customers',
        'first_order_only',
        'fulfilment_scope',
        'weekdays',
        'daily_starts_at',
        'daily_ends_at',
        'customer_message',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'discount_type' => PromotionDiscountType::class,
            'fulfilment_scope' => PromotionFulfilmentScope::class,
            'discount_value' => 'decimal:2',
            'minimum_subtotal' => 'decimal:2',
            'maximum_discount_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'applies_to_all_products' => 'boolean',
            'applies_to_all_customers' => 'boolean',
            'first_order_only' => 'boolean',
            'weekdays' => 'array',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'promotion_product_category');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'promotion_user');
    }

    public function orderPromotions(): HasMany
    {
        return $this->hasMany(OrderPromotion::class);
    }

    public function displayLabel(): string
    {
        return filled($this->customer_message) ? (string) $this->customer_message : (string) $this->name;
    }
}
