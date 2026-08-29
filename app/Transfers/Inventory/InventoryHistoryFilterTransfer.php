<?php

namespace App\Transfers\Inventory;

class InventoryHistoryFilterTransfer implements InventoryHistoryFilterTransferInterface
{
    protected ?int $ingredientId = null;

    protected ?int $ingredientCategoryId = null;

    protected ?int $ingredientBrandId = null;

    protected ?string $transactionType = null;

    protected ?int $createdBy = null;

    protected ?string $dateFrom = null;

    protected ?string $dateTo = null;

    public function getIngredientId(): ?int
    {
        return $this->ingredientId;
    }

    public function setIngredientId(?int $ingredientId): void
    {
        $this->ingredientId = $ingredientId;
    }

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

    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    public function setTransactionType(?string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getDateFrom(): ?string
    {
        return $this->dateFrom;
    }

    public function setDateFrom(?string $dateFrom): void
    {
        $this->dateFrom = $dateFrom;
    }

    public function getDateTo(): ?string
    {
        return $this->dateTo;
    }

    public function setDateTo(?string $dateTo): void
    {
        $this->dateTo = $dateTo;
    }

    public function toArray(): array
    {
        return array_filter([
            'ingredient_id' => $this->ingredientId,
            'ingredient_category_id' => $this->ingredientCategoryId,
            'ingredient_brand_id' => $this->ingredientBrandId,
            'transaction_type' => $this->transactionType,
            'created_by' => $this->createdBy,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
