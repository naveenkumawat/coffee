<?php

namespace App\Transfers\Menu;

use Illuminate\Support\Str;

readonly class MenuCategoryData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public int $sortOrder,
        public bool $isActive,
        public ?string $slug = null,
    ) {}

    public static function fromArray(array $attributes): self
    {
        return new self(
            name: trim($attributes['name']),
            description: filled($attributes['description'] ?? null) ? trim($attributes['description']) : null,
            sortOrder: (int) ($attributes['sort_order'] ?? 0),
            isActive: (bool) ($attributes['is_active'] ?? true),
            slug: filled($attributes['slug'] ?? null) ? Str::slug($attributes['slug']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug ?: Str::slug($this->name),
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }
}
