<?php

namespace App\Transfers\Menu;

use App\Transfers\AbstractTransfer;
use Illuminate\Support\Str;

class MenuItemTransfer extends AbstractTransfer implements MenuItemTransferInterface
{
    protected int $menuCategoryId = 0;

    protected ?string $name = null;

    protected ?string $slug = null;

    protected ?string $description = null;

    protected string $price = '0.00';

    protected bool $isAvailable = true;

    protected bool $isFeatured = false;

    protected int $sortOrder = 0;

    public function getMenuCategoryId(): int
    {
        return $this->menuCategoryId;
    }

    public function setMenuCategoryId(int $menuCategoryId): void
    {
        $this->menuCategoryId = $menuCategoryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): void
    {
        $this->price = $price;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): void
    {
        $this->isAvailable = $isAvailable;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): void
    {
        $this->isFeatured = $isFeatured;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function toArray(): array
    {
        return [
            'menu_category_id' => $this->menuCategoryId,
            'name' => $this->name,
            'slug' => filled($this->slug) ? Str::slug($this->slug) : Str::slug((string) $this->name),
            'description' => $this->description,
            'price' => number_format((float) $this->price, 2, '.', ''),
            'is_available' => $this->isAvailable,
            'is_featured' => $this->isFeatured,
            'sort_order' => $this->sortOrder,
        ];
    }
}
