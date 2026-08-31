<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\CafeTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Tax\TaxCalculatorInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
                'pickup_name' => $definition['pickup_name'] ?? ($definition['fulfilment_method'] === 'delivery' ? null : $customer->name),
                'pickup_phone' => $definition['pickup_phone'] ?? ($definition['fulfilment_method'] === 'delivery' ? null : $customer->phone),
                'assigned_barista_id' => $definition['assigned_barista_id'],
                'checkout_token' => $definition['checkout_token'],
                'status' => $definition['status'],
                'subtotal' => $definition['subtotal'],
                'discount_total' => '0.00',
                'total_amount' => $definition['total_amount'],
                'customer_notes' => $definition['customer_notes'],
                'pickup_notes' => $definition['pickup_notes'] ?? null,
                'fulfilment_method' => $definition['fulfilment_method'] ?? 'takeaway',
                'cafe_table_id' => $definition['cafe_table_id'] ?? null,
                'table_name_snapshot' => $definition['table_name_snapshot'] ?? null,
                'delivery_address' => $definition['delivery_address'] ?? null,
                'delivery_phone' => $definition['delivery_phone'] ?? null,
                'delivery_contact_name' => $definition['delivery_contact_name'] ?? null,
                'delivery_notes' => $definition['delivery_notes'] ?? null,
                'delivery_provider' => $definition['delivery_provider'] ?? null,
                'delivery_fee_amount' => $definition['delivery_fee_amount'] ?? null,
                'delivery_tracking_reference' => $definition['delivery_tracking_reference'] ?? null,
                'payment_method' => $definition['payment_method'] ?? 'manual',
                'payment_status' => $definition['payment_status'] ?? 'pending',
                'payment_reference' => $definition['payment_reference'] ?? null,
                'payment_proof_path' => $definition['payment_proof_path'] ?? null,
                'payment_proof_disk' => $definition['payment_proof_disk'] ?? null,
                'payment_proof_mime' => $definition['payment_proof_mime'] ?? null,
                'payment_proof_size' => $definition['payment_proof_size'] ?? null,
                'payment_proof_uploaded_at' => $definition['payment_proof_uploaded_at'] ?? null,
                'payment_proof_rejection_notes' => $definition['payment_proof_rejection_notes'] ?? null,
                'payment_received_by_id' => $definition['payment_received_by_id'] ?? null,
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

        $subtotal = '0.00';

        foreach ($definition['items'] as $item) {
            $product = Product::query()->where('name', $item['product'])->firstOrFail();
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('name', $item['variant'])
                ->firstOrFail();
            $recipe = Recipe::query()->where('product_variant_id', $variant->id)->first();
            $lineSubtotal = bcmul((string) $variant->price, (string) $item['quantity'], 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);

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

        $order->forceFill([
            'subtotal' => $subtotal,
            'total_amount' => $subtotal,
        ])->save();

        $tax = app(TaxCalculatorInterface::class)->calculateForTaxableAmount($subtotal);
        $deliveryFee = $definition['delivery_fee_amount'] ?? null;
        $totalAmount = app(TaxCalculatorInterface::class)->payableTotal(
            $tax,
            is_string($deliveryFee) ? $deliveryFee : null,
        );

        $order->forceFill([
            'subtotal' => $subtotal,
            'discount_total' => '0.00',
            'tax_enabled_snapshot' => $tax->enabled,
            'tax_label_snapshot' => $tax->enabled ? $tax->label : null,
            'tax_percent_snapshot' => $tax->enabled ? $tax->percent : null,
            'tax_inclusive_snapshot' => $tax->enabled ? $tax->inclusive : false,
            'taxable_amount' => $tax->taxableAmount,
            'tax_amount' => $tax->taxAmount,
            'total_amount' => $totalAmount,
        ])->save();

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

        if (filled($definition['payment_proof_path'] ?? null)) {
            Storage::disk($definition['payment_proof_disk'] ?? 'local')->put(
                (string) $definition['payment_proof_path'],
                'demo-payment-proof',
            );
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
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'pending',
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
                'status' => OrderStatus::PendingPayment,
                'fulfilment_method' => 'delivery',
                'delivery_address' => "14 Park Avenue\nIndiranagar, Bengaluru 560038",
                'delivery_phone' => '9111111111',
                'delivery_contact_name' => $priya->name,
                'delivery_notes' => 'Leave with security if needed.',
                'delivery_fee_amount' => null,
                'payment_status' => 'awaiting_review',
                'payment_proof_path' => 'payment-proofs/demo/seed-0002.jpg',
                'payment_proof_disk' => 'local',
                'payment_proof_mime' => 'image/jpeg',
                'payment_proof_size' => 2048,
                'payment_proof_uploaded_at' => $today->setTime(10, 10),
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-pending-with-proof'),
                'subtotal' => '5.25',
                'total_amount' => '5.25',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $today->setTime(10, 5),
                'payment_confirmed_at' => null,
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
                        'notes' => 'Delivery order placed; payment proof uploaded.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0003',
                'daily_sequence' => 3,
                'customer' => $arjun,
                'status' => OrderStatus::Accepted,
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'confirmed',
                'payment_proof_path' => 'payment-proofs/demo/seed-0003.jpg',
                'payment_proof_disk' => 'local',
                'payment_proof_mime' => 'image/jpeg',
                'payment_proof_size' => 1800,
                'payment_proof_uploaded_at' => $today->setTime(11, 6),
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
                        'notes' => 'Payment screenshot verified.',
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
                'order_number' => 'CC-SEED-0009',
                'daily_sequence' => 9,
                'customer' => $customer,
                'status' => OrderStatus::PaymentConfirmed,
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'confirmed',
                'payment_proof_path' => 'payment-proofs/demo/seed-0009.jpg',
                'payment_proof_disk' => 'local',
                'payment_proof_mime' => 'image/jpeg',
                'payment_proof_size' => 1600,
                'payment_proof_uploaded_at' => $today->setTime(10, 20),
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-payment-confirmed-only'),
                'subtotal' => '4.50',
                'total_amount' => '4.50',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $today->setTime(10, 15),
                'payment_confirmed_at' => $today->setTime(10, 22),
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Americano', 'variant' => 'Regular', 'quantity' => 1],
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
                        'notes' => 'Payment confirmed; awaiting barista acceptance.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0004',
                'daily_sequence' => 4,
                'customer' => $customer,
                'status' => OrderStatus::Preparing,
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'confirmed',
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
                'fulfilment_method' => 'delivery',
                'payment_status' => 'confirmed',
                'delivery_address' => "88 Indiranagar 100 Feet Road\nBengaluru 560038",
                'delivery_phone' => $priya->phone,
                'delivery_contact_name' => $priya->name,
                'delivery_notes' => 'Leave with concierge if needed.',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-ready'),
                'subtotal' => '9.25',
                'total_amount' => '9.25',
                'customer_notes' => null,
                'pickup_notes' => null,
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
                        'notes' => 'Delivery order placed.',
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
                        'notes' => 'Ready for third-party delivery handover.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0006',
                'daily_sequence' => 1,
                'customer' => $customer,
                'status' => OrderStatus::Completed,
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'confirmed',
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
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'pending',
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
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'confirmed',
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
            ...$this->dineInOrders($customer, $priya, $arjun, $barista, $admin, $today, $yesterday),
            [
                'order_number' => 'CC-SEED-0010',
                'daily_sequence' => 10,
                'customer' => $customer,
                'status' => OrderStatus::PendingPayment,
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'rejected',
                'payment_proof_path' => 'payment-proofs/demo/seed-0010.jpg',
                'payment_proof_disk' => 'local',
                'payment_proof_mime' => 'image/jpeg',
                'payment_proof_size' => 1500,
                'payment_proof_uploaded_at' => $today->setTime(8, 40),
                'payment_proof_rejection_notes' => 'Screenshot is cropped — please re-upload the full UPI confirmation.',
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-checkout-proof-rejected'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $today->setTime(8, 30),
                'payment_confirmed_at' => null,
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Flat White', 'variant' => 'Regular', 'quantity' => 1],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $customer->id,
                        'notes' => 'Order placed; payment proof rejected — awaiting replacement.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0011',
                'daily_sequence' => 81,
                'customer' => $arjun,
                'status' => OrderStatus::Completed,
                'fulfilment_method' => 'takeaway',
                'payment_status' => 'confirmed',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-week-ago'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $today->subDays(6)->setTime(15, 10),
                'payment_confirmed_at' => $today->subDays(6)->setTime(15, 15),
                'accepted_at' => $today->subDays(6)->setTime(15, 18),
                'preparing_at' => $today->subDays(6)->setTime(15, 20),
                'ready_for_pickup_at' => $today->subDays(6)->setTime(15, 30),
                'completed_at' => $today->subDays(6)->setTime(15, 40),
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Cafe Latte', 'variant' => 'Large', 'quantity' => 2],
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
                        'notes' => 'Collected.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-SEED-0012',
                'daily_sequence' => 91,
                'customer' => $priya,
                'status' => OrderStatus::Completed,
                'fulfilment_method' => 'delivery',
                'delivery_address' => "22 Koramangala 5th Block\nBengaluru 560095",
                'delivery_phone' => $priya->phone,
                'delivery_contact_name' => $priya->name,
                'payment_status' => 'confirmed',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-checkout-month-ago'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'pickup_notes' => null,
                'placed_at' => $today->subDays(20)->setTime(12, 0),
                'payment_confirmed_at' => $today->subDays(20)->setTime(12, 10),
                'accepted_at' => $today->subDays(20)->setTime(12, 12),
                'preparing_at' => $today->subDays(20)->setTime(12, 15),
                'ready_for_pickup_at' => $today->subDays(20)->setTime(12, 28),
                'completed_at' => $today->subDays(20)->setTime(13, 5),
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Mocha Frappe', 'variant' => 'Large', 'quantity' => 1],
                    ['product' => 'Chocolate Muffin', 'variant' => 'Single', 'quantity' => 2],
                ],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $priya->id,
                        'notes' => 'Delivery order placed.',
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
                        'notes' => 'Ready for handover.',
                    ],
                    [
                        'from' => OrderStatus::ReadyForPickup,
                        'to' => OrderStatus::Completed,
                        'changed_by' => $barista->id,
                        'notes' => 'Handed to delivery partner.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function dineInOrders(
        User $customer,
        User $priya,
        User $arjun,
        User $barista,
        User $admin,
        CarbonImmutable $today,
        CarbonImmutable $yesterday,
    ): array {
        $tables = CafeTable::query()->whereIn('code', ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'])->get()->keyBy('code');

        if ($tables->count() < 7) {
            return [];
        }

        $historyThrough = function (User $actor, User $adminUser, User $baristaUser, OrderStatus $final) {
            $steps = [
                [null, OrderStatus::PendingPayment, $actor->id, 'Dine-in order placed.'],
            ];

            if ($final === OrderStatus::PendingPayment) {
                return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
            }

            $steps[] = [OrderStatus::PendingPayment, OrderStatus::PaymentConfirmed, $adminUser->id, 'Payment confirmed.'];

            if ($final === OrderStatus::PaymentConfirmed) {
                return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
            }

            $steps[] = [OrderStatus::PaymentConfirmed, OrderStatus::Accepted, $baristaUser->id, 'Accepted.'];

            if ($final === OrderStatus::Accepted) {
                return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
            }

            $steps[] = [OrderStatus::Accepted, OrderStatus::Preparing, $baristaUser->id, 'Preparing for table.'];

            if ($final === OrderStatus::Preparing) {
                return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
            }

            $steps[] = [OrderStatus::Preparing, OrderStatus::ReadyForPickup, $baristaUser->id, 'Ready to serve.'];

            if ($final === OrderStatus::ReadyForPickup) {
                return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
            }

            $steps[] = [OrderStatus::ReadyForPickup, OrderStatus::Completed, $baristaUser->id, 'Served and completed.'];

            if ($final === OrderStatus::Completed) {
                return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
            }

            return array_map(fn ($s) => ['from' => $s[0], 'to' => $s[1], 'changed_by' => $s[2], 'notes' => $s[3]], $steps);
        };

        return [
            [
                'order_number' => 'CC-DINE-0001',
                'daily_sequence' => 21,
                'customer' => $customer,
                'status' => OrderStatus::PendingPayment,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T1']->id,
                'table_name_snapshot' => $tables['T1']->snapshotLabel(),
                'payment_status' => 'pending',
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-dine-pending-t1'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => 'Seated at T1',
                'placed_at' => $today->setTime(9, 40),
                'payment_confirmed_at' => null,
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Espresso', 'variant' => 'Double', 'quantity' => 1]],
                'history' => $historyThrough($customer, $admin, $barista, OrderStatus::PendingPayment),
            ],
            [
                'order_number' => 'CC-DINE-0002',
                'daily_sequence' => 22,
                'customer' => $priya,
                'status' => OrderStatus::PaymentConfirmed,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T2']->id,
                'table_name_snapshot' => $tables['T2']->snapshotLabel(),
                'payment_status' => 'confirmed',
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-dine-paid-t2'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'placed_at' => $today->setTime(10, 35),
                'payment_confirmed_at' => $today->setTime(10, 42),
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Cafe Latte', 'variant' => 'Regular', 'quantity' => 2]],
                'history' => $historyThrough($priya, $admin, $barista, OrderStatus::PaymentConfirmed),
            ],
            [
                'order_number' => 'CC-DINE-0003',
                'daily_sequence' => 23,
                'customer' => $arjun,
                'status' => OrderStatus::Accepted,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T3']->id,
                'table_name_snapshot' => $tables['T3']->snapshotLabel(),
                'payment_status' => 'confirmed',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-dine-accepted-t3'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'placed_at' => $today->setTime(11, 20),
                'payment_confirmed_at' => $today->setTime(11, 25),
                'accepted_at' => $today->setTime(11, 28),
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Cappuccino', 'variant' => 'Regular', 'quantity' => 1]],
                'history' => $historyThrough($arjun, $admin, $barista, OrderStatus::Accepted),
            ],
            [
                'order_number' => 'CC-DINE-0004',
                'daily_sequence' => 24,
                'customer' => $customer,
                'status' => OrderStatus::Preparing,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T4']->id,
                'table_name_snapshot' => $tables['T4']->snapshotLabel(),
                'payment_status' => 'confirmed',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-dine-preparing-t4'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'placed_at' => $today->setTime(12, 5),
                'payment_confirmed_at' => $today->setTime(12, 10),
                'accepted_at' => $today->setTime(12, 12),
                'preparing_at' => $today->setTime(12, 15),
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [
                    ['product' => 'Hazelnut Latte', 'variant' => 'Regular', 'quantity' => 1],
                    ['product' => 'Butter Croissant', 'variant' => 'Single', 'quantity' => 1],
                ],
                'history' => $historyThrough($customer, $admin, $barista, OrderStatus::Preparing),
            ],
            [
                'order_number' => 'CC-DINE-0005',
                'daily_sequence' => 25,
                'customer' => $priya,
                'status' => OrderStatus::ReadyForPickup,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T5']->id,
                'table_name_snapshot' => $tables['T5']->snapshotLabel(),
                'payment_status' => 'confirmed',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-dine-ready-t5'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'placed_at' => $today->setTime(13, 0),
                'payment_confirmed_at' => $today->setTime(13, 5),
                'accepted_at' => $today->setTime(13, 6),
                'preparing_at' => $today->setTime(13, 8),
                'ready_for_pickup_at' => $today->setTime(13, 18),
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Virgin Mojito', 'variant' => '300 ml', 'quantity' => 2]],
                'history' => $historyThrough($priya, $admin, $barista, OrderStatus::ReadyForPickup),
            ],
            [
                'order_number' => 'CC-DINE-0006',
                'daily_sequence' => 61,
                'customer' => $arjun,
                'status' => OrderStatus::Completed,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T6']->id,
                'table_name_snapshot' => $tables['T6']->snapshotLabel(),
                'payment_status' => 'confirmed',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-dine-completed-t6'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'placed_at' => $yesterday->setTime(17, 0),
                'payment_confirmed_at' => $yesterday->setTime(17, 5),
                'accepted_at' => $yesterday->setTime(17, 7),
                'preparing_at' => $yesterday->setTime(17, 10),
                'ready_for_pickup_at' => $yesterday->setTime(17, 20),
                'completed_at' => $yesterday->setTime(17, 35),
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Matcha Latte', 'variant' => 'Regular', 'quantity' => 1]],
                'history' => $historyThrough($arjun, $admin, $barista, OrderStatus::Completed),
            ],
            [
                'order_number' => 'CC-DINE-0007',
                'daily_sequence' => 71,
                'customer' => $customer,
                'status' => OrderStatus::Cancelled,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T7']->id,
                'table_name_snapshot' => $tables['T7']->snapshotLabel(),
                'payment_status' => 'pending',
                'assigned_barista_id' => null,
                'checkout_token' => hash('sha256', 'demo-dine-cancelled-t7'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => 'Left the table early.',
                'placed_at' => $yesterday->setTime(11, 10),
                'payment_confirmed_at' => null,
                'accepted_at' => null,
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => $yesterday->setTime(11, 25),
                'rejected_at' => null,
                'items' => [['product' => 'Iced Americano', 'variant' => 'Regular', 'quantity' => 1]],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $customer->id,
                        'notes' => 'Dine-in order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::Cancelled,
                        'changed_by' => $customer->id,
                        'notes' => 'Customer cancelled before payment.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-CASH-0001',
                'daily_sequence' => 81,
                'customer' => $customer,
                'status' => OrderStatus::Accepted,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T1']->id,
                'table_name_snapshot' => $tables['T1']->snapshotLabel(),
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-cash-dine-pending'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => 'Cash at table — not yet collected.',
                'placed_at' => $today->setTime(8, 20),
                'payment_confirmed_at' => null,
                'accepted_at' => $today->setTime(8, 25),
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Espresso', 'variant' => 'Single', 'quantity' => 1]],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $customer->id,
                        'notes' => 'Dine-in cash order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted before cash collected.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-CASH-0002',
                'daily_sequence' => 82,
                'customer' => $priya,
                'status' => OrderStatus::Preparing,
                'fulfilment_method' => 'dine_in',
                'cafe_table_id' => $tables['T2']->id,
                'table_name_snapshot' => $tables['T2']->snapshotLabel(),
                'payment_method' => 'cash',
                'payment_status' => 'confirmed',
                'payment_received_by_id' => $barista->id,
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-cash-dine-received'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'placed_at' => $today->setTime(8, 45),
                'payment_confirmed_at' => $today->setTime(8, 55),
                'accepted_at' => $today->setTime(8, 50),
                'preparing_at' => $today->setTime(8, 58),
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Cafe Latte', 'variant' => 'Regular', 'quantity' => 1]],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $priya->id,
                        'notes' => 'Dine-in cash order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted before cash collected.',
                    ],
                    [
                        'from' => OrderStatus::Accepted,
                        'to' => OrderStatus::Preparing,
                        'changed_by' => $barista->id,
                        'notes' => 'Preparing after cash received.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-CASH-0003',
                'daily_sequence' => 83,
                'customer' => $priya,
                'status' => OrderStatus::Accepted,
                'fulfilment_method' => 'takeaway',
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-cash-takeaway-pending'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => 'Trusted takeaway — cash at pickup.',
                'pickup_notes' => 'Name on cup: Priya',
                'placed_at' => $today->setTime(9, 5),
                'payment_confirmed_at' => null,
                'accepted_at' => $today->setTime(9, 10),
                'preparing_at' => null,
                'ready_for_pickup_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Cappuccino', 'variant' => 'Regular', 'quantity' => 1]],
                'history' => [
                    [
                        'from' => null,
                        'to' => OrderStatus::PendingPayment,
                        'changed_by' => $priya->id,
                        'notes' => 'Takeaway cash order placed.',
                    ],
                    [
                        'from' => OrderStatus::PendingPayment,
                        'to' => OrderStatus::Accepted,
                        'changed_by' => $barista->id,
                        'notes' => 'Accepted — collect cash at pickup.',
                    ],
                ],
            ],
            [
                'order_number' => 'CC-CASH-0004',
                'daily_sequence' => 84,
                'customer' => $arjun,
                'status' => OrderStatus::ReadyForPickup,
                'fulfilment_method' => 'takeaway',
                'payment_method' => 'cash',
                'payment_status' => 'confirmed',
                'payment_received_by_id' => $barista->id,
                'assigned_barista_id' => $barista->id,
                'checkout_token' => hash('sha256', 'demo-cash-takeaway-received'),
                'subtotal' => '0.00',
                'total_amount' => '0.00',
                'customer_notes' => null,
                'pickup_notes' => 'Paid in cash at counter.',
                'placed_at' => $today->setTime(9, 30),
                'payment_confirmed_at' => $today->setTime(9, 50),
                'accepted_at' => $today->setTime(9, 35),
                'preparing_at' => $today->setTime(9, 40),
                'ready_for_pickup_at' => $today->setTime(9, 48),
                'completed_at' => null,
                'cancelled_at' => null,
                'rejected_at' => null,
                'items' => [['product' => 'Flat White', 'variant' => 'Regular', 'quantity' => 1]],
                'history' => $historyThrough($arjun, $admin, $barista, OrderStatus::ReadyForPickup),
            ],
        ];
    }
}
