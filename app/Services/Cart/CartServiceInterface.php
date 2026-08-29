<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use App\Transfers\Cart\CartItemTransferInterface;

interface CartServiceInterface
{
    public function getForCustomer(User $customer): Cart;

    public function addItem(User $customer, CartItemTransferInterface $data): Cart;

    public function updateItem(User $customer, CartItem $cartItem, CartItemTransferInterface $data): Cart;

    public function removeItem(User $customer, CartItem $cartItem): Cart;

    public function clear(User $customer): Cart;

    public function count(User $customer): int;

    public function summarize(Cart $cart): array;
}
