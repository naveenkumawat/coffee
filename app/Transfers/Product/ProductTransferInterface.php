<?php

namespace App\Transfers\Product;

interface ProductTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getProductCategoryId(): ?int;

    public function setProductCategoryId(?int $productCategoryId): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getSku(): ?string;

    public function setSku(?string $sku): void;

    public function getShortDescription(): ?string;

    public function setShortDescription(?string $shortDescription): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getCustomerIngredientSummary(): ?string;

    public function setCustomerIngredientSummary(?string $customerIngredientSummary): void;

    public function getImagePath(): ?string;

    public function setImagePath(?string $imagePath): void;

    public function getPreparationTimeMinutes(): ?int;

    public function setPreparationTimeMinutes(?int $preparationTimeMinutes): void;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): void;

    public function getProductFlavourIds(): array;

    public function setProductFlavourIds(array $productFlavourIds): void;

    public function getVariants(): array;

    public function setVariants(array $variants): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function isAvailable(): bool;

    public function setIsAvailable(bool $isAvailable): void;

    public function isFeatured(): bool;

    public function setIsFeatured(bool $isFeatured): void;

    public function isNew(): bool;

    public function setIsNew(bool $isNew): void;

    public function isBestseller(): bool;

    public function setIsBestseller(bool $isBestseller): void;

    public function isVegetarian(): bool;

    public function setIsVegetarian(bool $isVegetarian): void;

    public function isCustomizable(): bool;

    public function setIsCustomizable(bool $isCustomizable): void;

    public function toArray(): array;
}
