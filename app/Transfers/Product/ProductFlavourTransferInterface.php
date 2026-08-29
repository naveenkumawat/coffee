<?php

namespace App\Transfers\Product;

interface ProductFlavourTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getImagePath(): ?string;

    public function setImagePath(?string $imagePath): void;

    public function getProductCategoryIds(): array;

    public function setProductCategoryIds(array $productCategoryIds): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function toArray(): array;
}
