<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ProductRatingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $customers = User::query()
            ->whereIn('email', [
                'customer@coffee.local',
                'priya@coffee.local',
                'arjun@coffee.local',
            ])
            ->get()
            ->keyBy('email');

        if ($customers->isEmpty()) {
            return;
        }

        $definitions = [
            [
                'email' => 'customer@coffee.local',
                'product' => 'Americano',
                'rating' => 5,
                'review' => 'Smooth and strong — my weekday staple.',
            ],
            [
                'email' => 'customer@coffee.local',
                'product' => 'Classic Masala Chai',
                'rating' => 4,
                'review' => 'Warm spices, just sweet enough.',
            ],
            [
                'email' => 'customer@coffee.local',
                'product' => 'Cafe Latte',
                'rating' => 5,
                'review' => null,
            ],
            [
                'email' => 'priya@coffee.local',
                'product' => 'Cold Brew',
                'rating' => 5,
                'review' => 'Clean finish, never bitter.',
            ],
            [
                'email' => 'priya@coffee.local',
                'product' => 'Iced Vanilla Latte',
                'rating' => 4,
                'review' => 'Vanilla comes through nicely on ice.',
            ],
            [
                'email' => 'priya@coffee.local',
                'product' => 'Matcha Latte',
                'rating' => 3,
                'review' => 'Good, a touch grassy for me.',
            ],
            [
                'email' => 'arjun@coffee.local',
                'product' => 'Mocha Frappe',
                'rating' => 5,
                'review' => 'Dessert in a cup. Will reorder.',
            ],
            [
                'email' => 'arjun@coffee.local',
                'product' => 'Butter Croissant',
                'rating' => 4,
                'review' => null,
            ],
            [
                'email' => 'arjun@coffee.local',
                'product' => 'Cafe Latte',
                'rating' => 4,
                'review' => 'Consistent milk texture every time.',
            ],
            [
                'email' => 'customer@coffee.local',
                'product' => 'Cold Brew',
                'rating' => 5,
                'review' => 'Perfect over ice after lunch.',
            ],
        ];

        foreach ($definitions as $definition) {
            /** @var User|null $customer */
            $customer = $customers->get($definition['email']);
            $product = Product::query()->where('name', $definition['product'])->first();

            if (! $customer || ! $product) {
                continue;
            }

            $order = $this->ensureCompletedPurchase($customer, $product);

            ProductRating::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                ],
                [
                    'qualifying_order_id' => $order->id,
                    'rating' => $definition['rating'],
                    'review' => $definition['review'],
                    'is_public' => true,
                    'moderated_at' => null,
                    'moderated_by' => null,
                ],
            );
        }
    }

    protected function ensureCompletedPurchase(User $customer, Product $product): Order
    {
        $existingOrderId = Order::query()
            ->where('customer_id', $customer->id)
            ->where('status', OrderStatus::Completed)
            ->whereHas('items', fn ($query) => $query->where('product_id', $product->id))
            ->orderByDesc('completed_at')
            ->value('id');

        if ($existingOrderId) {
            return Order::query()->findOrFail($existingOrderId);
        }

        $variant = $product->defaultVariant
            ?? ProductVariant::query()->where('product_id', $product->id)->orderBy('sort_order')->first();

        $placedAt = CarbonImmutable::now()->subDays(random_int(2, 14))->setTime(11, 30);
        $unitPrice = $variant?->price ?? '8.00';

        $order = Order::query()->create([
            'order_number' => 'CC-RATE-'.$customer->id.'-'.$product->id,
            'order_date' => $placedAt->toDateString(),
            'daily_sequence' => random_int(50, 900),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
            'status' => OrderStatus::Completed,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual',
            'payment_status' => PaymentStatus::Confirmed,
            'subtotal' => $unitPrice,
            'discount_total' => '0.00',
            'total_amount' => $unitPrice,
            'placed_at' => $placedAt,
            'payment_confirmed_at' => $placedAt->addMinutes(5),
            'accepted_at' => $placedAt->addMinutes(8),
            'preparing_at' => $placedAt->addMinutes(10),
            'ready_for_pickup_at' => $placedAt->addMinutes(18),
            'completed_at' => $placedAt->addMinutes(30),
            'checkout_token' => hash('sha256', 'rating-seed-'.$customer->id.'-'.$product->id),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'recipe_id' => $variant?->recipe_id,
            'product_name' => $product->name,
            'variant_name' => $variant?->name ?? 'Regular',
            'customer_ingredient_summary' => $product->customer_ingredient_summary,
            'unit_price' => $unitPrice,
            'quantity' => 1,
            'line_subtotal' => $unitPrice,
        ]);

        return $order;
    }
}
