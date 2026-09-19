<?php

namespace App\Models;

use App\Enums\CmsPageKey;
use Database\Factories\CmsPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends AbstractModel
{
    /** @use HasFactory<CmsPageFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'title',
        'seo_title',
        'meta_description',
        'body',
        'is_published',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function pageKey(): ?CmsPageKey
    {
        return CmsPageKey::tryFrom((string) $this->key);
    }

    /**
     * @return HasMany<CmsFaqItem, $this>
     */
    public function faqItems(): HasMany
    {
        return $this->hasMany(CmsFaqItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
