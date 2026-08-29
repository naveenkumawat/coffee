<?php

namespace App\Transfers\Cart;

use App\Transfers\AbstractTransfer;

class CartItemTransfer extends AbstractTransfer implements CartItemTransferInterface
{
    protected ?int $productVariantId = null;

    protected int $quantity = 1;

    public function getProductVariantId(): ?int
    {
        return $this->productVariantId;
    }

    public function setProductVariantId(?int $productVariantId): void
    {
        $this->productVariantId = $productVariantId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function toArray(): array
    {
        return [
            'product_variant_id' => $this->productVariantId,
            'quantity' => $this->quantity,
        ];
    }
}
