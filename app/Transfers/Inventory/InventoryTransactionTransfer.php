<?php

namespace App\Transfers\Inventory;

use App\Transfers\AbstractTransfer;

class InventoryTransactionTransfer extends AbstractTransfer implements InventoryTransactionTransferInterface
{
    protected ?int $ingredientId = null;

    protected ?string $transactionType = null;

    protected ?string $quantity = null;

    protected ?string $measurementUnit = null;

    protected ?string $referenceType = null;

    protected ?int $referenceId = null;

    protected ?string $notes = null;

    protected ?int $createdBy = null;

    public function getIngredientId(): ?int
    {
        return $this->ingredientId;
    }

    public function setIngredientId(?int $ingredientId): void
    {
        $this->ingredientId = $ingredientId;
    }

    public function getTransactionType(): ?string
    {
        return $this->transactionType;
    }

    public function setTransactionType(?string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(?string $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getMeasurementUnit(): ?string
    {
        return $this->measurementUnit;
    }

    public function setMeasurementUnit(?string $measurementUnit): void
    {
        $this->measurementUnit = $measurementUnit;
    }

    public function getReferenceType(): ?string
    {
        return $this->referenceType;
    }

    public function setReferenceType(?string $referenceType): void
    {
        $this->referenceType = $referenceType;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function setReferenceId(?int $referenceId): void
    {
        $this->referenceId = $referenceId;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function toArray(): array
    {
        return [
            'ingredient_id' => $this->ingredientId,
            'transaction_type' => $this->transactionType,
            'quantity' => $this->quantity,
            'measurement_unit' => $this->measurementUnit,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
            'notes' => $this->notes,
            'created_by' => $this->createdBy,
        ];
    }
}
