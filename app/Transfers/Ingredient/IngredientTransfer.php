<?php

namespace App\Transfers\Ingredient;

use App\Transfers\AbstractTransfer;

class IngredientTransfer extends AbstractTransfer implements IngredientTransferInterface
{
    protected ?int $ingredientCategoryId = null;

    protected ?int $ingredientBrandId = null;

    protected ?string $name = null;

    protected ?string $slug = null;

    protected ?string $description = null;

    protected ?string $measurementUnit = null;

    protected ?string $purchaseQuantity = null;

    protected ?string $purchaseCost = null;

    protected ?string $currentStock = null;

    protected ?string $minimumStock = null;

    protected ?string $reorderLevel = null;

    protected ?string $supplierName = null;

    protected ?string $supplierEmail = null;

    protected ?string $supplierPhone = null;

    protected ?string $supplierNotes = null;

    protected bool $isActive = true;

    public function getIngredientCategoryId(): ?int
    {
        return $this->ingredientCategoryId;
    }

    public function setIngredientCategoryId(?int $ingredientCategoryId): void
    {
        $this->ingredientCategoryId = $ingredientCategoryId;
    }

    public function getIngredientBrandId(): ?int
    {
        return $this->ingredientBrandId;
    }

    public function setIngredientBrandId(?int $ingredientBrandId): void
    {
        $this->ingredientBrandId = $ingredientBrandId;
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

    public function getMeasurementUnit(): ?string
    {
        return $this->measurementUnit;
    }

    public function setMeasurementUnit(?string $measurementUnit): void
    {
        $this->measurementUnit = $measurementUnit;
    }

    public function getPurchaseQuantity(): ?string
    {
        return $this->purchaseQuantity;
    }

    public function setPurchaseQuantity(?string $purchaseQuantity): void
    {
        $this->purchaseQuantity = $purchaseQuantity;
    }

    public function getPurchaseCost(): ?string
    {
        return $this->purchaseCost;
    }

    public function setPurchaseCost(?string $purchaseCost): void
    {
        $this->purchaseCost = $purchaseCost;
    }

    public function getCurrentStock(): ?string
    {
        return $this->currentStock;
    }

    public function setCurrentStock(?string $currentStock): void
    {
        $this->currentStock = $currentStock;
    }

    public function getMinimumStock(): ?string
    {
        return $this->minimumStock;
    }

    public function setMinimumStock(?string $minimumStock): void
    {
        $this->minimumStock = $minimumStock;
    }

    public function getReorderLevel(): ?string
    {
        return $this->reorderLevel;
    }

    public function setReorderLevel(?string $reorderLevel): void
    {
        $this->reorderLevel = $reorderLevel;
    }

    public function getSupplierName(): ?string
    {
        return $this->supplierName;
    }

    public function setSupplierName(?string $supplierName): void
    {
        $this->supplierName = $supplierName;
    }

    public function getSupplierEmail(): ?string
    {
        return $this->supplierEmail;
    }

    public function setSupplierEmail(?string $supplierEmail): void
    {
        $this->supplierEmail = $supplierEmail;
    }

    public function getSupplierPhone(): ?string
    {
        return $this->supplierPhone;
    }

    public function setSupplierPhone(?string $supplierPhone): void
    {
        $this->supplierPhone = $supplierPhone;
    }

    public function getSupplierNotes(): ?string
    {
        return $this->supplierNotes;
    }

    public function setSupplierNotes(?string $supplierNotes): void
    {
        $this->supplierNotes = $supplierNotes;
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
            'ingredient_category_id' => $this->ingredientCategoryId,
            'ingredient_brand_id' => $this->ingredientBrandId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'measurement_unit' => $this->measurementUnit,
            'purchase_quantity' => $this->purchaseQuantity,
            'purchase_cost' => $this->purchaseCost,
            'current_stock' => $this->currentStock,
            'minimum_stock' => $this->minimumStock,
            'reorder_level' => $this->reorderLevel,
            'supplier_name' => $this->supplierName,
            'supplier_email' => $this->supplierEmail,
            'supplier_phone' => $this->supplierPhone,
            'supplier_notes' => $this->supplierNotes,
            'is_active' => $this->isActive,
        ];
    }
}
