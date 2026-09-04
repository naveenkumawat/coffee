<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAddOn extends AbstractModel
{
    protected $table = 'product_add_on';

    protected $fillable = [
        'product_id',
        'add_on_id',
        'price_override',
        'max_quantity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'max_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function addOn(): BelongsTo
    {
        return $this->belongsTo(AddOn::class);
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(ProductAddOnRecipeLine::class)->orderBy('sort_order');
    }

    public function variantRecipeLines(): HasMany
    {
        return $this->hasMany(ProductVariantAddOnRecipeLine::class);
    }
}
