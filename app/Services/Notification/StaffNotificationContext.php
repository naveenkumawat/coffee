<?php

namespace App\Services\Notification;

use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\Order;
use App\Models\OrderPreparation;

final class StaffNotificationContext
{
    public function __construct(
        public ?Order $order = null,
        public ?OrderPreparation $preparation = null,
        public ?Ingredient $ingredient = null,
        public ?InventoryRefillRequest $refillRequest = null,
    ) {}

    public static function forOrder(Order $order): self
    {
        return new self(order: $order);
    }

    public static function forPreparation(OrderPreparation $preparation): self
    {
        $preparation->loadMissing('order');

        return new self(
            order: $preparation->order,
            preparation: $preparation,
        );
    }

    public static function forIngredient(Ingredient $ingredient): self
    {
        return new self(ingredient: $ingredient);
    }

    public static function forRefillRequest(InventoryRefillRequest $refillRequest): self
    {
        $refillRequest->loadMissing('ingredient');

        return new self(
            ingredient: $refillRequest->ingredient,
            refillRequest: $refillRequest,
        );
    }
}
