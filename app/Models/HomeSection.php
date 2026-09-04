<?php

namespace App\Models;

use App\Enums\HomeSectionPlacement;
use App\Enums\HomeSectionSourceType;
use Database\Factories\HomeSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'placement',
        'source_type',
        'source_category_id',
        'source_tag_id',
        'recommendation_context',
        'priority',
        'targeting_rules',
        'starts_at',
        'ends_at',
        'dedupe_products',
        'fallback_to_curated',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'max_items' => 'integer',
            'placement' => HomeSectionPlacement::class,
            'source_type' => HomeSectionSourceType::class,
            'source_category_id' => 'integer',
            'source_tag_id' => 'integer',
            'priority' => 'integer',
            'targeting_rules' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'dedupe_products' => 'boolean',
            'fallback_to_curated' => 'boolean',
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

    public function sourceCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'source_category_id');
    }

    public function sourceTag(): BelongsTo
    {
        return $this->belongsTo(ProductTag::class, 'source_tag_id');
    }
}
