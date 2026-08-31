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
use App\Services\Tax\TaxCalculatorInterface;
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
                'neha@coffee.local',
                'rohan@coffee.local',
                'meera@coffee.local',
                'kabir@coffee.local',
                'ananya@coffee.local',
                'vikram@coffee.local',
                'sara@coffee.local',
            ])
            ->get()
            ->keyBy('email');

        if ($customers->isEmpty()) {
            return;
        }

        foreach ($this->definitions() as $definition) {
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

    /**
     * @return list<array{email: string, product: string, rating: int, review: ?string}>
     */
    protected function definitions(): array
    {
        return [
            ['email' => 'customer@coffee.local', 'product' => 'Americano', 'rating' => 5, 'review' => 'Smooth and strong — my weekday staple.'],
            ['email' => 'customer@coffee.local', 'product' => 'Classic Masala Chai', 'rating' => 4, 'review' => 'Warm spices, just sweet enough.'],
            ['email' => 'customer@coffee.local', 'product' => 'Cafe Latte', 'rating' => 5, 'review' => null],
            ['email' => 'customer@coffee.local', 'product' => 'Cold Brew', 'rating' => 5, 'review' => 'Perfect over ice after lunch.'],
            ['email' => 'customer@coffee.local', 'product' => 'Espresso', 'rating' => 4, 'review' => 'Clean crema.'],
            ['email' => 'customer@coffee.local', 'product' => 'Cappuccino', 'rating' => 5, 'review' => 'Foam was textbook.'],
            ['email' => 'priya@coffee.local', 'product' => 'Cold Brew', 'rating' => 5, 'review' => 'Clean finish, never bitter.'],
            ['email' => 'priya@coffee.local', 'product' => 'Iced Vanilla Latte', 'rating' => 4, 'review' => 'Vanilla comes through nicely on ice.'],
            ['email' => 'priya@coffee.local', 'product' => 'Matcha Latte', 'rating' => 3, 'review' => 'Good, a touch grassy for me.'],
            ['email' => 'priya@coffee.local', 'product' => 'Virgin Mojito', 'rating' => 5, 'review' => 'Refreshing after a walk.'],
            ['email' => 'arjun@coffee.local', 'product' => 'Mocha Frappe', 'rating' => 5, 'review' => 'Dessert in a cup. Will reorder.'],
            ['email' => 'arjun@coffee.local', 'product' => 'Butter Croissant', 'rating' => 4, 'review' => null],
            ['email' => 'arjun@coffee.local', 'product' => 'Cafe Latte', 'rating' => 4, 'review' => 'Consistent milk texture every time.'],
            ['email' => 'arjun@coffee.local', 'product' => 'Hazelnut Latte', 'rating' => 5, 'review' => 'Hazelnut without being syrupy.'],
            ['email' => 'neha@coffee.local', 'product' => 'Caramel Latte', 'rating' => 5, 'review' => 'Sweet but balanced.'],
            ['email' => 'neha@coffee.local', 'product' => 'Iced Latte', 'rating' => 4, 'review' => null],
            ['email' => 'neha@coffee.local', 'product' => 'Chocolate Muffin', 'rating' => 5, 'review' => 'Warm chocolate pockets.'],
            ['email' => 'rohan@coffee.local', 'product' => 'Flat White', 'rating' => 4, 'review' => 'Nice microfoam.'],
            ['email' => 'rohan@coffee.local', 'product' => 'Classic Cold Brew', 'rating' => 5, 'review' => null],
            ['email' => 'rohan@coffee.local', 'product' => 'Irish Latte', 'rating' => 3, 'review' => 'Interesting but rich.'],
            ['email' => 'meera@coffee.local', 'product' => 'Iced Matcha Latte', 'rating' => 5, 'review' => 'My summer go-to.'],
            ['email' => 'meera@coffee.local', 'product' => 'Strawberry Mojito', 'rating' => 4, 'review' => 'Bright and citrusy.'],
            ['email' => 'meera@coffee.local', 'product' => 'Vanilla Bean Frappe', 'rating' => 5, 'review' => null],
            ['email' => 'kabir@coffee.local', 'product' => 'Dark Cold Coffee', 'rating' => 4, 'review' => 'Bold enough for evenings.'],
            ['email' => 'kabir@coffee.local', 'product' => 'Mocha', 'rating' => 5, 'review' => 'Chocolate + coffee done right.'],
            ['email' => 'kabir@coffee.local', 'product' => 'Cold Brew Tonic', 'rating' => 4, 'review' => 'Unexpected and good.'],
            ['email' => 'ananya@coffee.local', 'product' => 'Hazelnut Frappe', 'rating' => 5, 'review' => null],
            ['email' => 'ananya@coffee.local', 'product' => 'Honey Lime Cold Brew', 'rating' => 4, 'review' => 'Honey softens the brew.'],
            ['email' => 'ananya@coffee.local', 'product' => 'Cafe Latte', 'rating' => 5, 'review' => 'Silky every visit.'],
            ['email' => 'vikram@coffee.local', 'product' => 'Caramel Crunch Frappe', 'rating' => 3, 'review' => 'A bit sweet for me.'],
            ['email' => 'vikram@coffee.local', 'product' => 'Americano', 'rating' => 4, 'review' => null],
            ['email' => 'vikram@coffee.local', 'product' => 'Mix Berry Lemonade', 'rating' => 5, 'review' => 'Great with lunch.'],
            ['email' => 'sara@coffee.local', 'product' => 'Cafe Latte', 'rating' => 5, 'review' => 'My comfort order.'],
            ['email' => 'sara@coffee.local', 'product' => 'Blueberry Mojito', 'rating' => 4, 'review' => null],
            ['email' => 'sara@coffee.local', 'product' => 'Iced Americano', 'rating' => 5, 'review' => 'Crisp and light.'],
        ];
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

        $dayOffset = 2 + (($customer->id + $product->id) % 20);
        $placedAt = CarbonImmutable::now()->subDays($dayOffset)->setTime(11, 30);
        $unitPrice = $variant?->price ?? '8.00';
        $dailySequence = 2000 + ((int) $customer->id * 40) + (int) $product->id;
        $tax = app(TaxCalculatorInterface::class)->calculateForTaxableAmount((string) $unitPrice);

        $order = Order::query()->create([
            'order_number' => 'CC-RATE-'.$customer->id.'-'.$product->id,
            'order_date' => $placedAt->toDateString(),
            'daily_sequence' => $dailySequence,
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
            'tax_enabled_snapshot' => $tax->enabled,
            'tax_label_snapshot' => $tax->enabled ? $tax->label : null,
            'tax_percent_snapshot' => $tax->enabled ? $tax->percent : null,
            'tax_inclusive_snapshot' => $tax->enabled ? $tax->inclusive : false,
            'taxable_amount' => $tax->taxableAmount,
            'tax_amount' => $tax->taxAmount,
            'total_amount' => $tax->cafeTotal,
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
