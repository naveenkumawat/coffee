<?php

namespace App\Models;

use App\Enums\PreparationStation;
use App\Enums\ProductType;
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
        'is_new',
        'is_bestseller',
        'is_vegetarian',
        'is_customizable',
        'product_type',
        'preparation_station',
    ];

    protected function casts(): array
    {
        return [
            'preparation_time_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_vegetarian' => 'boolean',
            'is_customizable' => 'boolean',
            'product_type' => ProductType::class,
            'preparation_station' => PreparationStation::class,
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

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag')
            ->withTimestamps();
    }

    public function addOns(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'product_add_on')
            ->withPivot(['id', 'price_override', 'max_quantity', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
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

    public function favouritedByCustomers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'product_favourites', 'product_id', 'customer_id')
            ->withTimestamps();
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ProductRating::class);
    }
}
