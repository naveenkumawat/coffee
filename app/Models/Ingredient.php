<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use App\Enums\InventoryStockStatus;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends AbstractModel
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'ingredient_category_id',
        'ingredient_brand_id',
        'name',
        'slug',
        'description',
        'measurement_unit',
        'base_measurement_unit',
        'purchase_quantity',
        'purchase_quantity_base',
        'purchase_cost',
        'cost_per_unit',
        'current_stock',
        'minimum_stock',
        'reorder_level',
        'supplier_name',
        'supplier_email',
        'supplier_phone',
        'supplier_notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'measurement_unit' => IngredientUnit::class,
            'base_measurement_unit' => IngredientUnit::class,
            'purchase_quantity' => 'decimal:3',
            'purchase_quantity_base' => 'decimal:3',
            'purchase_cost' => 'decimal:2',
            'cost_per_unit' => 'decimal:4',
            'current_stock' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'ingredient_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(IngredientBrand::class, 'ingredient_brand_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function latestInventoryTransaction(): HasOne
    {
        return $this->hasOne(InventoryTransaction::class)->latestOfMany();
    }

    public function stockStatus(): InventoryStockStatus
    {
        if (bccomp((string) $this->current_stock, '0', 3) <= 0) {
            return InventoryStockStatus::OutOfStock;
        }

        $warningThreshold = bccomp((string) $this->reorder_level, '0', 3) === 1
            ? (string) $this->reorder_level
            : (string) $this->minimum_stock;

        if (bccomp($warningThreshold, '0', 3) === 1
            && bccomp((string) $this->current_stock, $warningThreshold, 3) <= 0) {
            return InventoryStockStatus::LowStock;
        }

        return InventoryStockStatus::InStock;
    }
}
