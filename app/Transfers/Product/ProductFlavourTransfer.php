<?php

namespace App\Transfers\Product;

use App\Transfers\AbstractTransfer;

class ProductFlavourTransfer extends AbstractTransfer implements ProductFlavourTransferInterface
{
    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $imagePath = null;

    protected array $productCategoryIds = [];

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

    public function getProductCategoryIds(): array
    {
        return $this->productCategoryIds;
    }

    public function setProductCategoryIds(array $productCategoryIds): void
    {
        $this->productCategoryIds = $productCategoryIds;
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
            'is_active' => $this->isActive,
        ];
    }
}
