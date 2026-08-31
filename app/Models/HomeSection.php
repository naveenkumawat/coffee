<?php

namespace App\Models;

use Database\Factories\HomeSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomeSection extends AbstractModel
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'title',
        'slug',
        'subtitle',
        'sort_order',
        'is_active',
        'max_items',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'max_items' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'home_section_products')
            ->withPivot(['id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('products.name');
    }

    public function sectionProducts(): HasMany
    {
        return $this->hasMany(HomeSectionProduct::class)->orderBy('sort_order')->orderBy('id');
    }
}
