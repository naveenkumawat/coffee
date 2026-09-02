<?php

namespace App\Transfers\Cart;

use App\Transfers\AbstractTransfer;

class CartItemTransfer extends AbstractTransfer implements CartItemTransferInterface
{
    protected ?int $productVariantId = null;

    protected int $quantity = 1;

    /** @var list<array{add_on_id: int, quantity: int}> */
    protected array $addOns = [];

    protected bool $addOnsProvided = false;

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

    public function getAddOns(): array
    {
        return $this->addOns;
    }

    public function hasAddOnsPayload(): bool
    {
        return $this->addOnsProvided;
    }

    public function setAddOns(array $addOns): void
    {
        $normalized = [];

        foreach ($addOns as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['add_on_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);

            if ($id <= 0 || $qty <= 0) {
                continue;
            }

            $normalized[] = [
                'add_on_id' => $id,
                'quantity' => $qty,
            ];
        }

        $this->addOns = $normalized;
        $this->addOnsProvided = true;
    }

    public function toArray(): array
    {
        return [
            'product_variant_id' => $this->productVariantId,
            'quantity' => $this->quantity,
            'add_ons' => $this->addOns,
        ];
    }
}
