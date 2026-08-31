<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRating>
 */
class ProductRatingFactory extends Factory
{
    protected $model = ProductRating::class;

    public function definition(): array
    {
        $rating = fake()->numberBetween(3, 5);

        return [
            'product_id' => Product::factory(),
            'customer_id' => User::factory()->customer(),
            'qualifying_order_id' => null,
            'rating' => $rating,
            'review' => fake()->optional(0.65)->sentence(12),
            'is_public' => true,
            'moderated_at' => null,
            'moderated_by' => null,
        ];
    }

    public function withReview(): static
    {
        return $this->state(fn (): array => [
            'review' => fake()->sentence(14),
        ]);
    }

    public function withoutReview(): static
    {
        return $this->state(fn (): array => [
            'review' => null,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'is_public' => false,
            'moderated_at' => now(),
        ]);
    }

    public function forPurchase(User $customer, Product $product, ?Order $order = null): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $customer->getKey(),
            'product_id' => $product->getKey(),
            'qualifying_order_id' => $order?->getKey(),
        ]);
    }
}
