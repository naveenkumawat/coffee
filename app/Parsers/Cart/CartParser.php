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

        if (array_key_exists('add_ons', $cartItemData)) {
            $transfer->setAddOns(is_array($cartItemData['add_ons']) ? $cartItemData['add_ons'] : []);
        }

        if (array_key_exists('attribution', $cartItemData) && is_array($cartItemData['attribution'])) {
            $transfer->setAttribution($cartItemData['attribution']);
        }

        if (filled($cartItemData['visitor_key'] ?? null)) {
            $transfer->setVisitorKey((string) $cartItemData['visitor_key']);
        }

        return $transfer;
    }
}
