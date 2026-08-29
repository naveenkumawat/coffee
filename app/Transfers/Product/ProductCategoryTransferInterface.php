<?php

namespace App\Transfers\Product;

interface ProductCategoryTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getImagePath(): ?string;

    public function setImagePath(?string $imagePath): void;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function toArray(): array;
}
