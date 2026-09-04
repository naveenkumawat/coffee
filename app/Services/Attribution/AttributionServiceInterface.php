<?php

namespace App\Services\Attribution;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;

interface AttributionServiceInterface
{
    /**
     * @param  array<string, mixed>|null  $claimed
     * @return array<string, mixed>|null
     */
    public function resolveForCartAdd(
        ?array $claimed,
        int $productId,
        ?User $customer = null,
        ?string $visitorKey = null,
    ): ?array;

    /**
     * @param  array<string, mixed>  $attribution
     */
    public function stampCartItem(
        CartItem $cartItem,
        array $attribution,
        ?User $customer = null,
        ?string $visitorKey = null,
        int $quantityAdded = 1,
    ): void;

    /**
     * @param  array<string, mixed>|null  $attribution
     * @return array<string, mixed>|null
     */
    public function snapshotForOrderItem(?array $attribution): ?array;

    public function recordConversionsForOrder(Order $order): void;

    public function orderIsConversionEligible(Order $order): bool;
}
