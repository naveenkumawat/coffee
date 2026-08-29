<?php

namespace App\Transfers\Product;

interface ProductFlavourFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function getProductCategoryId(): ?int;

    public function setProductCategoryId(?int $productCategoryId): void;

    public function toArray(): array;
}
