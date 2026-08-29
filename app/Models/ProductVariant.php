<?php

namespace App\Models;

use App\Enums\ProductServingUnit;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends AbstractModel
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'serving_size_value',
        'serving_size_unit',
        'price',
        'sort_order',
        'is_active',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'serving_size_unit' => ProductServingUnit::class,
            'serving_size_value' => 'decimal:3',
            'price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class)->whereNull('deleted_at');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_variant_id');
    }
}
