<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductFavourite;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCustomerActivitySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $customer = User::query()->where('email', 'customer@coffee.local')->first();
        $priya = User::query()->where('email', 'priya@coffee.local')->first();

        if ($customer) {
            $this->seedFavourites($customer, [
                'Cafe Latte',
                'Iced Vanilla Latte',
                'Mocha Frappe',
                'Matcha Latte',
            ]);
            $this->seedCart($customer, [
                ['product' => 'Cafe Latte', 'variant' => 'Large', 'quantity' => 1],
                ['product' => 'Butter Croissant', 'variant' => 'Single', 'quantity' => 2],
            ]);
        }

        if ($priya) {
            $this->seedFavourites($priya, [
                'Cold Brew',
                'Classic Masala Chai',
            ]);
            $this->seedCart($priya, [
                ['product' => 'Iced Vanilla Latte', 'variant' => 'Regular', 'quantity' => 1],
                ['product' => 'Vanilla Bean Frappe', 'variant' => 'Regular', 'quantity' => 1],
            ]);
        }
    }

    /**
     * @param  list<string>  $productNames
     */
    protected function seedFavourites(User $customer, array $productNames): void
    {
        foreach ($productNames as $name) {
            $product = Product::query()->where('name', $name)->first();

            if (! $product) {
                continue;
            }

            ProductFavourite::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                ],
                [],
            );
        }
    }

    /**
     * @param  list<array{product: string, variant: string, quantity: int}>  $items
     */
    protected function seedCart(User $customer, array $items): void
    {
        $cart = Cart::query()->updateOrCreate(
            ['customer_id' => $customer->id],
            [],
        );

        $cart->items()->delete();

        foreach ($items as $item) {
            $product = Product::query()->where('name', $item['product'])->first();

            if (! $product) {
                continue;
            }

            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('name', $item['variant'])
                ->first();

            if (! $variant) {
                continue;
            }

            $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity' => $item['quantity'],
            ]);
        }
    }
}
