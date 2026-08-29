<?php

namespace App\Transfers\Cart;

interface CartItemTransferInterface
{
    public function getProductVariantId(): ?int;

    public function setProductVariantId(?int $productVariantId): void;

    public function getQuantity(): int;

    public function setQuantity(int $quantity): void;

    public function toArray(): array;
}
