<?php

namespace App\Transfers\Inventory;

interface InventoryHistoryFilterTransferInterface
{
    public function getIngredientId(): ?int;

    public function setIngredientId(?int $ingredientId): void;

    public function getIngredientCategoryId(): ?int;

    public function setIngredientCategoryId(?int $ingredientCategoryId): void;

    public function getIngredientBrandId(): ?int;

    public function setIngredientBrandId(?int $ingredientBrandId): void;

    public function getTransactionType(): ?string;

    public function setTransactionType(?string $transactionType): void;

    public function getCreatedBy(): ?int;

    public function setCreatedBy(?int $createdBy): void;

    public function getDateFrom(): ?string;

    public function setDateFrom(?string $dateFrom): void;

    public function getDateTo(): ?string;

    public function setDateTo(?string $dateTo): void;

    public function toArray(): array;
}
