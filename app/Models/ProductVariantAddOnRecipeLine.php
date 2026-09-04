<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantAddOnRecipeLine extends AbstractModel
{
    protected $fillable = [
        'product_variant_id',
        'product_add_on_id',
        'ingredient_id',
        'quantity',
        'measurement_unit',
        'base_quantity',
        'base_measurement_unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'measurement_unit' => IngredientUnit::class,
            'base_measurement_unit' => IngredientUnit::class,
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function productAddOn(): BelongsTo
    {
        return $this->belongsTo(ProductAddOn::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }
}
