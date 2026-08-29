<?php

namespace App\Parsers\Cart;

use App\Transfers\Cart\CartItemTransferInterface;

class CartParser implements CartParserInterface
{
    public function __construct(
        protected CartItemTransferInterface $transfer,
    ) {}

    public function getTransferFromArrayData(array $cartItemData): CartItemTransferInterface
    {
        $transfer = clone $this->transfer;
        $transfer->setProductVariantId(filled($cartItemData['product_variant_id'] ?? null) ? (int) $cartItemData['product_variant_id'] : null);
        $transfer->setQuantity(filled($cartItemData['quantity'] ?? null) ? (int) $cartItemData['quantity'] : 1);

        return $transfer;
    }
}
