<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends AbstractModel
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'product_variant_id',
        'version',
        'preparation_notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
