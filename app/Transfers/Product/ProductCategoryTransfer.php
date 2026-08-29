<?php

namespace App\Transfers\Product;

use App\Transfers\AbstractTransfer;

class ProductCategoryTransfer extends AbstractTransfer implements ProductCategoryTransferInterface
{
    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $imagePath = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): void
    {
        $this->imagePath = $imagePath;
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
            'description' => $this->description,
            'image_path' => $this->imagePath,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }
}
