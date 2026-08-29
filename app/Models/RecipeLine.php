<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use Database\Factories\RecipeLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeLine extends AbstractModel
{
    /** @use HasFactory<RecipeLineFactory> */
    use HasFactory;

    protected $fillable = [
        'recipe_id',
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

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class)->withTrashed();
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }
}
