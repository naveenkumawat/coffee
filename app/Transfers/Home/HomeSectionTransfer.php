<?php

namespace App\Transfers\Home;

use App\Transfers\AbstractTransfer;

class HomeSectionTransfer extends AbstractTransfer implements HomeSectionTransferInterface
{
    protected ?string $name = null;

    protected ?string $title = null;

    protected ?string $slug = null;

    protected ?string $subtitle = null;

    protected int $sortOrder = 0;

    protected bool $isActive = true;

    protected ?int $maxItems = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
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

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function setMaxItems(?int $maxItems): void
    {
        $this->maxItems = $maxItems;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'max_items' => $this->maxItems,
        ];
    }
}
