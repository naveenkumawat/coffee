<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\Cart\CartRepositoryInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Transfers\Cart\CartItemTransfer;
use App\Transfers\Cart\CartItemTransferInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService implements CartServiceInterface
{
    public function __construct(
        protected CartRepositoryInterface $carts,
        protected TaxCalculatorInterface $taxCalculator,
    ) {}

    public function getForCustomer(User $customer): Cart
    {
        return $this->carts->firstOrCreateForCustomer($customer);
    }

    public function addItem(User $customer, CartItemTransferInterface $data): Cart
    {
        return DB::transaction(function () use ($customer, $data): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);
            $variant = $this->validateVariant($data->getProductVariantId());
            $existingItem = $this->carts->findCustomerItem($cart, (int) $variant->getKey());

            if ($existingItem) {
                $this->carts->updateItem($existingItem, [
                    'quantity' => $existingItem->quantity + $data->getQuantity(),
                ]);
            } else {
                $this->carts->createItem($cart, [
                    'product_variant_id' => $variant->getKey(),
                    'quantity' => $data->getQuantity(),
                ]);
            }

            return $this->carts->refreshCart($cart);
        });
    }

    public function updateItem(User $customer, CartItem $cartItem, CartItemTransferInterface $data): Cart
    {
        return DB::transaction(function () use ($customer, $cartItem, $data): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);

            if ((int) $cartItem->cart_id !== (int) $cart->getKey()) {
                throw ValidationException::withMessages([
                    'item' => 'The selected cart item does not belong to the authenticated customer.',
                ]);
            }

            $this->validateVariant((int) $cartItem->product_variant_id);

            $this->carts->updateItem($cartItem, [
                'quantity' => $data->getQuantity(),
            ]);

            return $this->carts->refreshCart($cart);
        });
    }

    public function removeItem(User $customer, CartItem $cartItem): Cart
    {
        return DB::transaction(function () use ($customer, $cartItem): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);

            if ((int) $cartItem->cart_id !== (int) $cart->getKey()) {
                throw ValidationException::withMessages([
                    'item' => 'The selected cart item does not belong to the authenticated customer.',
                ]);
            }

            $this->carts->deleteItem($cartItem);

            return $this->carts->refreshCart($cart);
        });
    }

    public function clear(User $customer): Cart
    {
        return DB::transaction(function () use ($customer): Cart {
            $cart = $this->carts->firstOrCreateForCustomer($customer);
            $this->carts->clearItems($cart);

            return $this->carts->refreshCart($cart);
        });
    }

    public function mergeGuestItems(User $customer, array $items, ?string $idempotencyKey = null): Cart
    {
        $cacheKey = null;

        if (filled($idempotencyKey)) {
            $cacheKey = sprintf('cart-merge:%d:%s', (int) $customer->getKey(), $idempotencyKey);

            if (Cache::has($cacheKey)) {
                return $this->getForCustomer($customer);
            }
        }

        $quantitiesByVariant = [];

        foreach ($items as $item) {
            $variantId = (int) $item['product_variant_id'];
            $quantitiesByVariant[$variantId] = ($quantitiesByVariant[$variantId] ?? 0) + (int) $item['quantity'];
        }

        $cart = DB::transaction(function () use ($customer, $quantitiesByVariant): Cart {
            foreach ($quantitiesByVariant as $variantId => $quantity) {
                $transfer = new CartItemTransfer;
                $transfer->setProductVariantId($variantId);
                $transfer->setQuantity($quantity);
                $this->addItem($customer, $transfer);
            }

            return $this->getForCustomer($customer);
        });

        if ($cacheKey !== null) {
            Cache::put($cacheKey, true, now()->addHour());
        }

        return $cart;
    }

    public function count(User $customer): int
    {
        $cart = $this->carts->findForCustomer($customer);

        if (! $cart) {
            return 0;
        }

        return $this->carts->countItems($cart);
    }

    public function summarize(Cart $cart): array
    {
        $subtotal = '0.00';
        $itemCount = 0;
        $hasUnavailableItems = false;

        foreach ($cart->items as $item) {
            $itemCount += (int) $item->quantity;

            if (! $this->isVariantAvailable($item->productVariant)) {
                $hasUnavailableItems = true;

                continue;
            }

            $subtotal = bcadd(
                $subtotal,
                bcmul($this->normalizeMoney((string) $item->productVariant->price), (string) $item->quantity, 2),
                2,
            );
        }

        $tax = $this->taxCalculator->calculateForTaxableAmount($subtotal);

        return [
            'item_count' => $itemCount,
            'subtotal' => $subtotal,
            'total' => $tax->cafeTotal,
            'tax' => $tax->toCustomerArray(),
            'has_unavailable_items' => $hasUnavailableItems,
        ];
    }

    protected function validateVariant(?int $productVariantId): ProductVariant
    {
        $variant = $productVariantId ? $this->carts->findPurchasableVariant($productVariantId) : null;

        if (! $this->isVariantAvailable($variant)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Only active and available product variants can be added to the cart.',
            ]);
        }

        return $variant;
    }

    protected function isVariantAvailable(?ProductVariant $variant): bool
    {
        return $variant instanceof ProductVariant
            && $variant->is_active
            && $variant->is_available
            && $variant->product !== null
            && $variant->product->is_active
            && $variant->product->is_available;
    }

    protected function normalizeMoney(string $value): string
    {
        return bcdiv($value, '1', 2);
    }
}
