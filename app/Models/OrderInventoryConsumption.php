<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInventoryConsumption extends AbstractModel
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'ingredient_id',
        'recipe_id',
        'recipe_line_id',
        'quantity',
        'base_quantity',
        'measurement_unit',
        'base_measurement_unit',
        'inventory_transaction_id',
        'reversal_inventory_transaction_id',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'measurement_unit' => IngredientUnit::class,
            'base_measurement_unit' => IngredientUnit::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class)->withTrashed();
    }

    public function recipeLine(): BelongsTo
    {
        return $this->belongsTo(RecipeLine::class);
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    public function reversalInventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'reversal_inventory_transaction_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null || $this->reversal_inventory_transaction_id !== null;
    }
}
