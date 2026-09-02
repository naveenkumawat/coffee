<?php

namespace App\Models;

use Database\Factories\AddOnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddOn extends AbstractModel
{
    /** @use HasFactory<AddOnFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'default_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function recipeLines(): HasMany
    {
        return $this->hasMany(AddOnRecipeLine::class)->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_add_on')
            ->withPivot(['id', 'price_override', 'max_quantity', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function resolvedPrice(?string $priceOverride = null): string
    {
        $price = $priceOverride !== null && $priceOverride !== ''
            ? $priceOverride
            : (string) $this->default_price;

        return bcdiv($price, '1', 2);
    }
}
