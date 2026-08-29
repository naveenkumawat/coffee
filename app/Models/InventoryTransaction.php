<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use Database\Factories\InventoryTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends AbstractModel
{
    /** @use HasFactory<InventoryTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'transaction_type',
        'quantity',
        'base_quantity',
        'measurement_unit',
        'base_measurement_unit',
        'stock_before',
        'stock_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => InventoryTransactionType::class,
            'measurement_unit' => IngredientUnit::class,
            'base_measurement_unit' => IngredientUnit::class,
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'stock_before' => 'decimal:3',
            'stock_after' => 'decimal:3',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
