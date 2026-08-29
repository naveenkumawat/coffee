<?php

namespace App\Transfers\Ingredient;

interface IngredientCategoryTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function toArray(): array;
}
