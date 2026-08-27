<?php

namespace App\Transfers\Menu;

use Illuminate\Support\Str;

readonly class MenuItemData
{
    public function __construct(
        public int $menuCategoryId,
        public string $name,
        public ?string $description,
        public string $price,
        public bool $isAvailable,
        public bool $isFeatured,
        public int $sortOrder,
        public ?string $slug = null,
    ) {}

    public static function fromArray(array $attributes): self
    {
        return new self(
            menuCategoryId: (int) $attributes['menu_category_id'],
            name: trim($attributes['name']),
            description: filled($attributes['description'] ?? null) ? trim($attributes['description']) : null,
            price: number_format((float) $attributes['price'], 2, '.', ''),
            isAvailable: (bool) ($attributes['is_available'] ?? true),
            isFeatured: (bool) ($attributes['is_featured'] ?? false),
            sortOrder: (int) ($attributes['sort_order'] ?? 0),
            slug: filled($attributes['slug'] ?? null) ? Str::slug($attributes['slug']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'menu_category_id' => $this->menuCategoryId,
            'name' => $this->name,
            'slug' => $this->slug ?: Str::slug($this->name),
            'description' => $this->description,
            'price' => $this->price,
            'is_available' => $this->isAvailable,
            'is_featured' => $this->isFeatured,
            'sort_order' => $this->sortOrder,
        ];
    }
}
