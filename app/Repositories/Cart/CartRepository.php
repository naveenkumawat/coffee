<?php

namespace App\Repositories\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\AbstractRepository;

class CartRepository extends AbstractRepository implements CartRepositoryInterface
{
    public function __construct(
        protected Cart $model,
        protected ProductVariant $variantModel,
    ) {}

    public function findForCustomer(User $customer): ?Cart
    {
        $cart = $this->model->newQuery()
            ->where('customer_id', $customer->getKey())
            ->first();

        return $cart ? $this->refreshCart($cart) : null;
    }

    public function firstOrCreateForCustomer(User $customer): Cart
    {
        $cart = $this->model->newQuery()->firstOrCreate([
            'customer_id' => $customer->getKey(),
        ]);

        return $this->refreshCart($cart);
    }

    public function findCustomerItem(Cart $cart, int $productVariantId): ?CartItem
    {
        return $cart->items()
            ->where('product_variant_id', $productVariantId)
            ->first();
    }

    public function findPurchasableVariant(int $productVariantId): ?ProductVariant
    {
        return $this->variantModel->newQuery()
            ->with(['product.category'])
            ->find($productVariantId);
    }

    public function createItem(Cart $cart, array $attributes): CartItem
    {
        /** @var CartItem $cartItem */
        $cartItem = $cart->items()->create($attributes);

        return $cartItem->fresh(['productVariant.product.category']);
    }

    public function updateItem(CartItem $cartItem, array $attributes): CartItem
    {
        /** @var CartItem $cartItem */
        $cartItem = $this->persist($cartItem, $attributes);

        return $cartItem->fresh(['productVariant.product.category']);
    }

    public function deleteItem(CartItem $cartItem): void
    {
        $this->remove($cartItem);
    }

    public function clearItems(Cart $cart): void
    {
        $cart->items()->delete();
    }

    public function refreshCart(Cart $cart): Cart
    {
        return $cart->fresh([
            'customer',
            'items.productVariant.product.category',
        ]);
    }

    public function countItems(Cart $cart): int
    {
        return (int) $cart->items()->sum('quantity');
    }
}
