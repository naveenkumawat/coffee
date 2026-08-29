<?php

namespace App\Transfers\Ingredient;

interface IngredientTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getIngredientCategoryId(): ?int;

    public function setIngredientCategoryId(?int $ingredientCategoryId): void;

    public function getIngredientBrandId(): ?int;

    public function setIngredientBrandId(?int $ingredientBrandId): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getMeasurementUnit(): ?string;

    public function setMeasurementUnit(?string $measurementUnit): void;

    public function getPurchaseQuantity(): ?string;

    public function setPurchaseQuantity(?string $purchaseQuantity): void;

    public function getPurchaseCost(): ?string;

    public function setPurchaseCost(?string $purchaseCost): void;

    public function getCurrentStock(): ?string;

    public function setCurrentStock(?string $currentStock): void;

    public function getMinimumStock(): ?string;

    public function setMinimumStock(?string $minimumStock): void;

    public function getReorderLevel(): ?string;

    public function setReorderLevel(?string $reorderLevel): void;

    public function getSupplierName(): ?string;

    public function setSupplierName(?string $supplierName): void;

    public function getSupplierEmail(): ?string;

    public function setSupplierEmail(?string $supplierEmail): void;

    public function getSupplierPhone(): ?string;

    public function setSupplierPhone(?string $supplierPhone): void;

    public function getSupplierNotes(): ?string;

    public function setSupplierNotes(?string $supplierNotes): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function toArray(): array;
}
