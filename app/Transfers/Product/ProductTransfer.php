<?php

namespace App\Transfers\Product;

use App\Transfers\AbstractTransfer;

class ProductTransfer extends AbstractTransfer implements ProductTransferInterface
{
    protected ?int $productCategoryId = null;

    protected ?string $name = null;

    protected ?string $sku = null;

    protected ?string $shortDescription = null;

    protected ?string $description = null;

    protected ?string $customerIngredientSummary = null;

    protected ?string $imagePath = null;

    protected ?int $preparationTimeMinutes = null;

    protected ?string $productType = null;

    protected ?string $preparationStation = null;

    protected int $sortOrder = 0;

    protected array $productFlavourIds = [];

    protected array $productTagIds = [];

    protected array $variants = [];

    protected bool $isActive = true;

    protected bool $isAvailable = true;

    protected bool $isFeatured = false;

    protected bool $isNew = false;

    protected bool $isBestseller = false;

    protected bool $isVegetarian = false;

    protected bool $isCustomizable = false;

    public function getProductCategoryId(): ?int
    {
        return $this->productCategoryId;
    }

    public function setProductCategoryId(?int $productCategoryId): void
    {
        $this->productCategoryId = $productCategoryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCustomerIngredientSummary(): ?string
    {
        return $this->customerIngredientSummary;
    }

    public function setCustomerIngredientSummary(?string $customerIngredientSummary): void
    {
        $this->customerIngredientSummary = $customerIngredientSummary;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): void
    {
        $this->imagePath = $imagePath;
    }

    public function getPreparationTimeMinutes(): ?int
    {
        return $this->preparationTimeMinutes;
    }

    public function setPreparationTimeMinutes(?int $preparationTimeMinutes): void
    {
        $this->preparationTimeMinutes = $preparationTimeMinutes;
    }

    public function getProductType(): ?string
    {
        return $this->productType;
    }

    public function setProductType(?string $productType): void
    {
        $this->productType = $productType;
    }

    public function getPreparationStation(): ?string
    {
        return $this->preparationStation;
    }

    public function setPreparationStation(?string $preparationStation): void
    {
        $this->preparationStation = $preparationStation;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getProductFlavourIds(): array
    {
        return $this->productFlavourIds;
    }

    public function setProductFlavourIds(array $productFlavourIds): void
    {
        $this->productFlavourIds = $productFlavourIds;
    }

    public function getProductTagIds(): array
    {
        return $this->productTagIds;
    }

    public function setProductTagIds(array $productTagIds): void
    {
        $this->productTagIds = $productTagIds;
    }

    public function getVariants(): array
    {
        return $this->variants;
    }

    public function setVariants(array $variants): void
    {
        $this->variants = $variants;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
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

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function setIsNew(bool $isNew): void
    {
        $this->isNew = $isNew;
    }

    public function isBestseller(): bool
    {
        return $this->isBestseller;
    }

    public function setIsBestseller(bool $isBestseller): void
    {
        $this->isBestseller = $isBestseller;
    }

    public function isVegetarian(): bool
    {
        return $this->isVegetarian;
    }

    public function setIsVegetarian(bool $isVegetarian): void
    {
        $this->isVegetarian = $isVegetarian;
    }

    public function isCustomizable(): bool
    {
        return $this->isCustomizable;
    }

    public function setIsCustomizable(bool $isCustomizable): void
    {
        $this->isCustomizable = $isCustomizable;
    }

    public function toArray(): array
    {
        return [
            'product_category_id' => $this->productCategoryId,
            'name' => $this->name,
            'sku' => $this->sku,
            'short_description' => $this->shortDescription,
            'description' => $this->description,
            'customer_ingredient_summary' => $this->customerIngredientSummary,
            'image_path' => $this->imagePath,
            'preparation_time_minutes' => $this->preparationTimeMinutes,
            'product_type' => $this->productType,
            'preparation_station' => $this->preparationStation,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'is_available' => $this->isAvailable,
            'is_featured' => $this->isFeatured,
            'is_new' => $this->isNew,
            'is_bestseller' => $this->isBestseller,
            'is_vegetarian' => $this->isVegetarian,
            'is_customizable' => $this->isCustomizable,
        ];
    }
}
