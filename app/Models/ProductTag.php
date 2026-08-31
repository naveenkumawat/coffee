<?php

namespace App\Models;

use App\Enums\ProductTagStyle;
use Database\Factories\ProductTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTag extends AbstractModel
{
    /** @use HasFactory<ProductTagFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'style_key',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'style_key' => ProductTagStyle::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_tag')
            ->withTimestamps();
    }
}
