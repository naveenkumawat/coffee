<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use App\Enums\InventoryRefillRequestStatus;
use Database\Factories\InventoryRefillRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRefillRequest extends AbstractModel
{
    /** @use HasFactory<InventoryRefillRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'ingredient_id',
        'quantity',
        'base_quantity',
        'measurement_unit',
        'base_measurement_unit',
        'notes',
        'requested_by',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryRefillRequestStatus::class,
            'measurement_unit' => IngredientUnit::class,
            'base_measurement_unit' => IngredientUnit::class,
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'reviewed_at' => 'datetime',
        ];
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
