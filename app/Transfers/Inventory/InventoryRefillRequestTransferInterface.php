<?php

namespace App\Transfers\Inventory;

interface InventoryRefillRequestTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getIngredientId(): ?int;

    public function setIngredientId(?int $ingredientId): void;

    public function getQuantity(): ?string;

    public function setQuantity(?string $quantity): void;

    public function getMeasurementUnit(): ?string;

    public function setMeasurementUnit(?string $measurementUnit): void;

    public function getNotes(): ?string;

    public function setNotes(?string $notes): void;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function getReviewNotes(): ?string;

    public function setReviewNotes(?string $reviewNotes): void;

    public function toArray(): array;
}
