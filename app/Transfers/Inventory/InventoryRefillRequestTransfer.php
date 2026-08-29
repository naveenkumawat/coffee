<?php

namespace App\Transfers\Inventory;

use App\Transfers\AbstractTransfer;

class InventoryRefillRequestTransfer extends AbstractTransfer implements InventoryRefillRequestTransferInterface
{
    protected ?int $ingredientId = null;

    protected ?string $quantity = null;

    protected ?string $measurementUnit = null;

    protected ?string $notes = null;

    protected ?string $status = null;

    protected ?string $reviewNotes = null;

    public function getIngredientId(): ?int
    {
        return $this->ingredientId;
    }

    public function setIngredientId(?int $ingredientId): void
    {
        $this->ingredientId = $ingredientId;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getReviewNotes(): ?string
    {
        return $this->reviewNotes;
    }

    public function setReviewNotes(?string $reviewNotes): void
    {
        $this->reviewNotes = $reviewNotes;
    }

    public function toArray(): array
    {
        return [
            'ingredient_id' => $this->ingredientId,
            'quantity' => $this->quantity,
            'measurement_unit' => $this->measurementUnit,
            'notes' => $this->notes,
            'status' => $this->status,
            'review_notes' => $this->reviewNotes,
        ];
    }
}
