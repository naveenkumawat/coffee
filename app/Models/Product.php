<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends AbstractModel
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'product_category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'customer_ingredient_summary',
        'image_path',
        'preparation_time_minutes',
        'sort_order',
        'is_active',
        'is_available',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'preparation_time_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id')->withTrashed();
    }

    public function flavours(): BelongsToMany
    {
        return $this->belongsToMany(ProductFlavour::class, 'product_flavour_product')
            ->withTimestamps();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('name');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->ofMany([
            'sort_order' => 'min',
            'id' => 'min',
        ], function ($query): void {
            $query->where('is_active', true)->where('is_available', true);
        });
    }
}
