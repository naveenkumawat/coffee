<?php

namespace App\Repositories\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;

interface CartRepositoryInterface
{
    public function findForCustomer(User $customer): ?Cart;

    public function firstOrCreateForCustomer(User $customer): Cart;

    public function findCustomerItem(Cart $cart, int $productVariantId): ?CartItem;

    public function findPurchasableVariant(int $productVariantId): ?ProductVariant;

    public function createItem(Cart $cart, array $attributes): CartItem;

    public function updateItem(CartItem $cartItem, array $attributes): CartItem;

    public function deleteItem(CartItem $cartItem): void;

    public function clearItems(Cart $cart): void;

    public function refreshCart(Cart $cart): Cart;

    public function countItems(Cart $cart): int;
}
