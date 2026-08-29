<?php

namespace App\Transfers\Recipe;

interface RecipeTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getProductVariantId(): ?int;

    public function setProductVariantId(?int $productVariantId): void;

    public function getPreparationNotes(): ?string;

    public function setPreparationNotes(?string $preparationNotes): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function getLines(): array;

    public function setLines(array $lines): void;

    public function toArray(): array;
}
