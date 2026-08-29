<?php

namespace App\Transfers\Inventory;

interface InventoryTransactionTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getIngredientId(): ?int;

    public function setIngredientId(?int $ingredientId): void;

    public function getTransactionType(): ?string;

    public function setTransactionType(?string $transactionType): void;

    public function getQuantity(): ?string;

    public function setQuantity(?string $quantity): void;

    public function getMeasurementUnit(): ?string;

    public function setMeasurementUnit(?string $measurementUnit): void;

    public function getReferenceType(): ?string;

    public function setReferenceType(?string $referenceType): void;

    public function getReferenceId(): ?int;

    public function setReferenceId(?int $referenceId): void;

    public function getNotes(): ?string;

    public function setNotes(?string $notes): void;

    public function getCreatedBy(): ?int;

    public function setCreatedBy(?int $createdBy): void;

    public function toArray(): array;
}
