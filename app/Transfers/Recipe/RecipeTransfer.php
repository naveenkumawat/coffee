<?php

namespace App\Transfers\Recipe;

use App\Transfers\AbstractTransfer;

class RecipeTransfer extends AbstractTransfer implements RecipeTransferInterface
{
    protected ?int $productVariantId = null;

    protected ?string $preparationNotes = null;

    protected bool $isActive = true;

    protected array $lines = [];

    public function getProductVariantId(): ?int
    {
        return $this->productVariantId;
    }

    public function setProductVariantId(?int $productVariantId): void
    {
        $this->productVariantId = $productVariantId;
    }

    public function getPreparationNotes(): ?string
    {
        return $this->preparationNotes;
    }

    public function setPreparationNotes(?string $preparationNotes): void
    {
        $this->preparationNotes = $preparationNotes;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function setLines(array $lines): void
    {
        $this->lines = $lines;
    }

    public function toArray(): array
    {
        return [
            'product_variant_id' => $this->productVariantId,
            'preparation_notes' => $this->preparationNotes,
            'is_active' => $this->isActive,
        ];
    }
}
