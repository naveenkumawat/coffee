<?php

namespace App\Transfers\Product;

interface ProductFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getProductCategoryId(): ?int;

    public function setProductCategoryId(?int $productCategoryId): void;

    public function getProductFlavourId(): ?int;

    public function setProductFlavourId(?int $productFlavourId): void;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function getAvailability(): ?string;

    public function setAvailability(?string $availability): void;

    public function getFeatured(): ?string;

    public function setFeatured(?string $featured): void;

    public function getReadiness(): ?string;

    public function setReadiness(?string $readiness): void;

    public function toArray(): array;
}
