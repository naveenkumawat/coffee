<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\AddOnConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => fake()->numberBetween(1, 4),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CartItem $item): void {
            if (filled($item->configuration_hash) || ! $item->product_variant_id) {
                return;
            }

            $item->configuration_hash = AddOnConfiguration::hash((int) $item->product_variant_id, []);
        });
    }
}
