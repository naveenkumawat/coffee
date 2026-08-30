<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $customer = User::query()->where('email', 'customer@coffee.local')->first();
        $priya = User::query()->where('email', 'priya@coffee.local')->first();
        $arjun = User::query()->where('email', 'arjun@coffee.local')->first();
        $barista = User::query()->where('email', 'barista@coffee.local')->first();
        $admin = User::query()->where('email', 'admin@coffee.local')->first();

        if (! $customer || ! $priya || ! $arjun || ! $barista || ! $admin) {
            return;
        }

        foreach ($this->orders($customer, $priya, $arjun, $barista, $admin) as $definition) {
            $this->seedOrder($definition);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function seedOrder(array $definition): void
    {
        /** @var User $customer */
        $customer = $definition['customer'];
        $placedAt = CarbonImmutable::parse($definition['placed_at']);

        $order = Order::query()->updateOrCreate(
            ['order_number' => $definition['order_number']],
            [
                'order_date' => $placedAt->toDateString(),
                'daily_sequence' => $definition['daily_sequence'],
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'pickup_name' => $definition['pickup_name'] ?? $customer->name,
                'pickup_phone' => $definition['pickup_phone'] ?? $customer->phone,
                'assigned_barista_id' => $definition['assigned_barista_id'],
                'checkout_token' => $definition['checkout_token'],
                'status' => $definition['status'],
                'subtotal' => $definition['subtotal'],
                'discount_total' => '0.00',
                'total_amount' => $definition['total_amount'],
                'customer_notes' => $definition['customer_notes'],
                'pickup_notes' => $definition['pickup_notes'],
                'placed_at' => $placedAt,
                'payment_confirmed_at' => $definition['payment_confirmed_at'],
                'accepted_at' => $definition['accepted_at'],
                'preparing_at' => $definition['preparing_at'],
                'ready_for_pickup_at' => $definition['ready_for_pickup_at'],
                'completed_at' => $definition['completed_at'],
                'cancelled_at' => $definition['cancelled_at'],
                'rejected_at' => $definition['rejected_at'],
            ],
        );

        $order->items()->delete();
        $order->statusHistory()->delete();

        foreach ($definition['items'] as $item) {
            $product = Product::query()->where('name', $item['product'])->firstOrFail();
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('name', $item['variant'])
                ->firstOrFail();
            $recipe = Recipe::query()->where('product_variant_id', $variant->id)->first();
            $lineSubtotal = bcmul((string) $variant->price, (string) $item['quantity'], 2);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'recipe_id' => $recipe?->id,
                'product_name' => $product->name,
                'variant_name' => $variant->name,
                'customer_ingredient_summary' => $product->customer_ingredient_summary,
                'unit_price' => $variant->price,
                'quantity' => $item['quantity'],
                'line_subtotal' => $lineSubtotal,
            ]);
        }

        foreach ($definition['history'] as $index => $history) {
            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => $history['from'],
                'to_status' => $history['to'],
                'changed_by' => $history['changed_by'],
                'notes' => $history['notes'],
                'created_at' => $placedAt->addMinutes(($index + 1) * 8),
                'updated_at' => $placedAt->addMinutes(($index + 1) * 8),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function orders(User $customer, User $priya, User $arjun, User $barista, User $admin): array
    {
        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();
        $twoDaysAgo = $today->subDays(2);
        $threeDaysAgo = $today->subDays(3);

        return [
            [
                'order_number' => 'CC-SEED-0001',
                'daily_sequence' => 1,
                'customer' => $customer,
                'status' => OrderStatus::PendingPayment,
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-pending-payment'),
                'subtotal' => '8.75',
                'total_amount' => '8.75',
                'customer_notes' => 'Please add oat milk if available.',
                'pickup_notes' => 'Name on cup: Demo',
                'placed_at' => $today->setTime(9, 15),
                'payment_confirmed_at' => null,
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Cafe Latte', 'variant' => 'Regular', 'quantity' => 1],
                    ['product' => 'Iced Americano', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $customer->id,
                        'notes' => 'Order placed; awaiting UPI confirmation.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0002',
                'daily_sequence' => 2,
                'customer' => $priya,
                'status' => OrderStatus::PaymentConfirmed,
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-payment-confirmed'),
                'subtotal' => '5.25',
                'total_amount' => '5.25',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $today->setTime(10, 5),
                'payment_confirmed_at' => $today->setTime(10, 12),
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Iced Vanilla Latte', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $priya->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::PaymentConfirmed,
                        'changed_by' => $admin->id,
                        'notes' => 'Payment screenshot verified.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0003',
                'daily_sequence' => 3,
                'customer' => $arjun,
                'status' => OrderStatus::Accepted,
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-accepted'),
                'subtotal' => '8.50',
                'total_amount' => '8.50',
                'customer_notes' => 'Extra hot.',
                'pickup_notes' => null,
                'placed_at' => $today->setTime(11, 0),
                'payment_confirmed_at' => $today->setTime(11, 8),
                'accepted_at' => $today->setTime(11, 10),
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Cappuccino', 'variant' => 'Large', 'quantity' => 1],
                    ['product' => 'Butter Croissant', 'variant' => 'Single', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $arjun->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::PaymentConfirmed,
                        'changed_by' => $admin->id,
                        'notes' => 'Payment confirmed.',
                    ],
                    [
                        'from' => OrderStatus::PaymentConfirmed,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted for preparation.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0004',
                'daily_sequence' => 4,
                'customer' => $customer,
                'status' => OrderStatus::Preparing,
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-preparing'),
                'subtotal' => '12.25',
                'total_amount' => '12.25',
                'customer_notes' => null,
                'pickup_notes' => 'Will arrive in 10 minutes.',
                'placed_at' => $today->setTime(12, 20),
                'payment_confirmed_at' => $today->setTime(12, 25),
                'accepted_at' => $today->setTime(12, 28),
                'preparing_at' => $today->setTime(12, 30),
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Mocha Frappe', 'variant' => 'Regular', 'quantity' => 1],
                    ['product' => 'Matcha Latte', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $customer->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::PaymentConfirmed,
                        'changed_by' => $admin->id,
                        'notes' => 'Payment confirmed.',
                    ],
                    [
                        'from' => OrderStatus::PaymentConfirmed,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted.',
                    ],
                    [
                        'from' => OrderStatus::Accepted,
                        'to' => OrderStatus::Preparing,
                        'changed_by' => $barista->id,
                        'notes' => 'Barista started preparation.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0005',
                'daily_sequence' => 5,
                'customer' => $priya,
                'status' => OrderStatus::ReadyForPickup,
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-ready'),
                'subtotal' => '9.25',
                'total_amount' => '9.25',
                'customer_notes' => null,
                'pickup_notes' => 'Counter pickup',
                'placed_at' => $today->setTime(13, 10),
                'payment_confirmed_at' => $today->setTime(13, 15),
                'accepted_at' => $today->setTime(13, 16),
                'preparing_at' => $today->setTime(13, 18),
                'ready_for_pickup_at' => $today->setTime(13, 28),
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Cold Brew', 'variant' => 'Regular', 'quantity' => 1],
                    ['product' => 'Chocolate Muffin', 'variant' => 'Single', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $priya->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::PaymentConfirmed,
                        'changed_by' => $admin->id,
                        'notes' => 'Payment confirmed.',
                    ],
                    [
                        'from' => OrderStatus::PaymentConfirmed,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted.',
                    ],
                    [
                        'from' => OrderStatus::Accepted,
                        'to' => OrderStatus::Preparing,
                        'changed_by' => $barista->id,
                        'notes' => 'Preparing.',
                    ],
                    [
                        'from' => OrderStatus::Preparing,
                        'to' => OrderStatus::ReadyForPickup,
                        'changed_by' => $barista->id,
                        'notes' => 'Ready at the pickup counter.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0006',
                'daily_sequence' => 1,
                'customer' => $customer,
                'status' => OrderStatus::Completed,
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-completed'),
                'subtotal' => '7.75',
                'total_amount' => '7.75',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $yesterday->setTime(16, 0),
                'payment_confirmed_at' => $yesterday->setTime(16, 5),
                'accepted_at' => $yesterday->setTime(16, 7),
                'preparing_at' => $yesterday->setTime(16, 10),
                'ready_for_pickup_at' => $yesterday->setTime(16, 18),
                'completed_at' => $yesterday->setTime(16, 30),
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Americano', 'variant' => 'Large', 'quantity' => 1],
                    ['product' => 'Classic Masala Chai', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $customer->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::PaymentConfirmed,
                        'changed_by' => $admin->id,
                        'notes' => 'Payment confirmed.',
                    ],
                    [
                        'from' => OrderStatus::PaymentConfirmed,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted.',
                    ],
                    [
                        'from' => OrderStatus::Accepted,
                        'to' => OrderStatus::Preparing,
                        'changed_by' => $barista->id,
                        'notes' => 'Preparing.',
                    ],
                    [
                        'from' => OrderStatus::Preparing,
                        'to' => OrderStatus::ReadyForPickup,
                        'changed_by' => $barista->id,
                        'notes' => 'Ready.',
                    ],
                    [
                        'from' => OrderStatus::ReadyForPickup,
                        'to' => OrderStatus::Completed,
                        'changed_by' => $barista->id,
                        'notes' => 'Collected by customer.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0007',
                'daily_sequence' => 1,
                'customer' => $arjun,
                'status' => OrderStatus::Cancelled,
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-cancelled'),
                'subtotal' => '6.50',
                'total_amount' => '6.50',
                'customer_notes' => 'Changed plans.',
                'pickup_notes' => null,
                'placed_at' => $twoDaysAgo->setTime(14, 40),
                'payment_confirmed_at' => null,
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => $twoDaysAgo->setTime(14, 55),
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Mocha Frappe', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $arjun->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::Cancelled,
                        'changed_by' => $arjun->id,
                        'notes' => 'Customer cancelled before payment.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0008',
                'daily_sequence' => 1,
                'customer' => $priya,
                'status' => OrderStatus::Rejected,
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-rejected'),
                'subtotal' => '5.95',
                'total_amount' => '5.95',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $threeDaysAgo->setTime(18, 20),
                'payment_confirmed_at' => $threeDaysAgo->setTime(18, 28),
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => $threeDaysAgo->setTime(18, 35),
                'items' => [
                    ['product' => 'Seasonal Spice Latte', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $priya->id,
                        'notes' => 'Order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::PaymentConfirmed,
                        'changed_by' => $admin->id,
                        'notes' => 'Payment confirmed.',
                    ],
                    [
                        'from' => OrderStatus::PaymentConfirmed,
                        'to' => OrderStatus::Rejected,
                        'changed_by' => $admin->id,
                        'notes' => 'Seasonal item unavailable; refund arranged offline.',
                    ],
                ],
            ],
        ];
    }
}
