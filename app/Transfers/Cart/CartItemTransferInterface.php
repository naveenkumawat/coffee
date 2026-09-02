<?php

namespace App\Transfers\Cart;

interface CartItemTransferInterface
{
    public function getProductVariantId(): ?int;

    public function setProductVariantId(?int $productVariantId): void;

    public function getQuantity(): int;

    public function setQuantity(int $quantity): void;

    /**
     * @return list<array{add_on_id: int, quantity: int}>
     */
    public function getAddOns(): array;

    public function hasAddOnsPayload(): bool;

    /**
     * @param  list<array{add_on_id?: int, quantity?: int}>  $addOns
     */
    public function setAddOns(array $addOns): void;

    public function toArray(): array;
}
