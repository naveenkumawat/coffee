<?php

namespace App\Transfers\Menu;

use App\Transfers\AbstractTransfer;
use Illuminate\Support\Str;

class MenuCategoryTransfer extends AbstractTransfer implements MenuCategoryTransferInterface
{
    protected ?string $name = null;

    protected ?string $slug = null;

    protected ?string $description = null;

    protected int $sortOrder = 0;

    protected bool $isActive = true;

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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => filled($this->slug) ? Str::slug($this->slug) : Str::slug((string) $this->name),
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }
}
