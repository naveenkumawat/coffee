<?php

namespace Tests\Feature;

use App\Enums\CustomerNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Events\Customer\CustomerPasswordChanged;
use App\Events\Customer\CustomerRegistered;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Models\CustomerNotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\CustomerPasswordChangedNotification;
use App\Notifications\CustomerResetPasswordNotification;
use App\Notifications\CustomerWelcomeNotification;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Services\Order\OrderServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTransactionalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('coffee.pwa.url', 'https://app.example.test');
        config()->set('coffee.company.name', 'The88Coffees');
    }

    public function test_welcome_notification_sends_once_on_registration(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create([
            'name' => 'Asha Guest',
            'email' => 'asha@example.test',
        ]);

        CustomerRegistered::dispatch($customer);
        CustomerRegistered::dispatch($customer);

        Notification::assertSentToTimes($customer, CustomerWelcomeNotification::class, 1);

        $mail = (new CustomerWelcomeNotification)->toMail($customer);
        $html = $mail->render();

        $this->assertStringContainsString('Welcome to The88Coffees', $mail->subject);
        $this->assertStringContainsString('https://app.example.test/menu', $html);
        $this->assertStringNotContainsString('password', strtolower($html));
        $this->assertSame(1, CustomerNotificationLog::query()->where('type', CustomerNotificationType::Welcome)->count());
    }

    public function test_password_reset_and_password_changed_notifications_are_branded_and_safe(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create([
            'email' => 'reset.me@example.test',
        ]);

        $customer->sendPasswordResetNotification('sample-token');

        Notification::assertSentTo(
            $customer,
            CustomerResetPasswordNotification::class,
            function (CustomerResetPasswordNotification $notification) use ($customer): bool {
                $mail = $notification->toMail($customer);
                $html = $mail->render();

                $this->assertStringContainsString('https://app.example.test/reset-password', $html);
                $this->assertStringContainsString('sample-token', $html);
                $this->assertStringNotContainsString((string) $customer->password, $html);

                return true;
            }
        );

        CustomerPasswordChanged::dispatch($customer);

        Notification::assertSentTo($customer, CustomerPasswordChangedNotification::class, function ($notification) use ($customer): bool {
            $html = $notification->toMail($customer)->render();

            $this->assertStringContainsString('password was changed', strtolower($html));
            $this->assertStringNotContainsString('sample-token', $html);

            return true;
        });
    }

    public function test_order_placed_and_status_emails_use_customer_app_url_and_are_idempotent(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create([
            'email' => 'buyer@example.test',
            'name' => 'Buyer One',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        OrderPlaced::dispatch($order);
        OrderPlaced::dispatch($order);

        Notification::assertSentToTimes($customer, OrderCustomerNotification::class, 1);

        $placed = new OrderCustomerNotification($order, CustomerNotificationType::OrderPlaced);
        $html = $placed->toMail($customer)->render();

        $this->assertStringContainsString('Order received — #'.$order->order_number, $placed->toMail($customer)->subject);
        $this->assertStringContainsString('https://app.example.test/orders/'.$order->id, $html);
        $this->assertStringContainsString('Pending Payment', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertStringNotContainsString('production cost', strtolower($html));
        $this->assertStringNotContainsString('gross profit', strtolower($html));
        $this->assertStringNotContainsString('recipe_id', strtolower($html));

        OrderStatusChanged::dispatch($order, OrderStatus::PendingPayment, OrderStatus::PaymentConfirmed);
        OrderStatusChanged::dispatch($order, OrderStatus::PendingPayment, OrderStatus::PaymentConfirmed);

        Notification::assertSentTo(
            $customer,
            OrderCustomerNotification::class,
            fn (OrderCustomerNotification $notification): bool => $notification->type === CustomerNotificationType::PaymentConfirmed,
        );

        $this->assertSame(
            1,
            CustomerNotificationLog::query()
                ->where('unique_key', 'order_status:'.$order->id.':payment_confirmed')
                ->where('status', 'sent')
                ->count(),
        );
    }

    public function test_ready_email_wording_depends_on_fulfilment_method(): void
    {
        Notification::fake();

        $customer = User::factory()->customer()->create(['email' => 'ready@example.test']);
        $takeaway = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);
        $delivery = $this->makeOrder($customer, OrderFulfilmentMethod::Delivery, [
            'delivery_address' => "12 Brew Lane\nCity",
        ]);

        $takeawayMail = (new OrderCustomerNotification($takeaway, CustomerNotificationType::OrderReady))->toMail($customer);
        $deliveryMail = (new OrderCustomerNotification($delivery, CustomerNotificationType::OrderReady))->toMail($customer);

        $this->assertStringContainsString('ready for pickup', strtolower($takeawayMail->subject));
        $this->assertStringContainsString('ready for delivery', strtolower($deliveryMail->subject));
        $this->assertStringContainsString('12 Brew Lane', $deliveryMail->render());
    }

    public function test_payment_proof_received_is_not_payment_confirmed(): void
    {
        Notification::fake();
        Storage::fake('local');

        $customer = User::factory()->customer()->create(['email' => 'proof@example.test']);
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        $orders = app(OrderServiceInterface::class);
        $updated = $orders->uploadPaymentProof(
            $order,
            $customer,
            UploadedFile::fake()->image('proof.jpg', 200, 200),
        );

        Notification::assertSentTo(
            $customer,
            OrderCustomerNotification::class,
            function (OrderCustomerNotification $notification) use ($customer): bool {
                if ($notification->type !== CustomerNotificationType::PaymentProofReceived) {
                    return false;
                }

                $html = $notification->toMail($customer)->render();

                $this->assertStringContainsString('Pending Payment', $html);
                $this->assertStringNotContainsString('Payment Confirmed', $html);

                return true;
            },
        );

        $orders->transition($updated, $admin, $this->statusTransfer(OrderStatus::PaymentConfirmed));

        Notification::assertSentTo(
            $customer,
            OrderCustomerNotification::class,
            fn (OrderCustomerNotification $notification): bool => $notification->type === CustomerNotificationType::PaymentConfirmed,
        );
    }

    public function test_payment_proof_rejection_sends_customer_safe_reason_only(): void
    {
        Notification::fake();
        Storage::fake('local');

        $customer = User::factory()->customer()->create(['email' => 'reject@example.test']);
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        $orders = app(OrderServiceInterface::class);
        $order = $orders->uploadPaymentProof(
            $order,
            $customer,
            UploadedFile::fake()->image('blurry.jpg', 120, 120),
        );

        $orders->rejectPaymentProof($order, $admin, 'Please upload a clearer screenshot.');

        Notification::assertSentTo(
            $customer,
            OrderCustomerNotification::class,
            function (OrderCustomerNotification $notification) use ($customer): bool {
                if ($notification->type !== CustomerNotificationType::PaymentProofRejected) {
                    return false;
                }

                $html = $notification->toMail($customer)->render();

                $this->assertStringContainsString('clearer screenshot', $html);
                $this->assertStringNotContainsString('internal', strtolower($html));

                return true;
            },
        );
    }

    public function test_mail_failure_does_not_block_order_status_transition(): void
    {
        $customer = User::factory()->customer()->create(['email' => 'fail@example.test']);
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
        ]);

        $this->app->bind(CustomerNotificationDispatcherInterface::class, CustomerNotificationDispatcherThrowingStub::class);

        $updated = app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame(OrderStatus::Accepted, $updated->status);
    }

    public function test_other_customers_do_not_receive_order_notifications(): void
    {
        Notification::fake();

        $owner = User::factory()->customer()->create(['email' => 'owner-order@example.test']);
        $stranger = User::factory()->customer()->create(['email' => 'stranger@example.test']);
        $order = $this->makeOrder($owner, OrderFulfilmentMethod::Takeaway);

        OrderPlaced::dispatch($order);

        Notification::assertSentTo($owner, OrderCustomerNotification::class);
        Notification::assertNotSentTo($stranger, OrderCustomerNotification::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeOrder(User $customer, OrderFulfilmentMethod $method, array $overrides = []): Order
    {
        $variant = $this->makePurchasableVariant();

        $order = Order::factory()
            ->when($method === OrderFulfilmentMethod::Delivery, fn ($factory) => $factory->delivery())
            ->when($method === OrderFulfilmentMethod::Takeaway, fn ($factory) => $factory->takeaway())
            ->create([
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'fulfilment_method' => $method,
                'status' => OrderStatus::PendingPayment,
                ...$overrides,
            ]);

        $order->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'recipe_id' => null,
            'preparation_station' => $variant->product->preparation_station?->value
                ?? PreparationStation::Bar->value,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->name,
            'customer_ingredient_summary' => null,
            'unit_price' => $variant->price,
            'quantity' => 1,
            'line_subtotal' => $variant->price,
        ]);

        return $order->fresh(['items', 'customer']);
    }

    protected function makePurchasableVariant(string $price = '9.50'): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => $price,
            'serving_size_value' => '250.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    protected function statusTransfer(OrderStatus $status): OrderStatusTransitionTransfer
    {
        $transfer = new OrderStatusTransitionTransfer;
        $transfer->setStatus($status->value);
        $transfer->setNotes(null);

        return $transfer;
    }
}

class CustomerNotificationDispatcherThrowingStub implements CustomerNotificationDispatcherInterface
{
    public function sendOnce(
        CustomerNotificationType $type,
        string $uniqueKey,
        string $recipientEmail,
        \Illuminate\Notifications\Notification $notification,
        ?User $customer = null,
        ?Order $order = null,
        ?string $customerFacingReason = null,
    ): bool {
        // Simulate handled failure without aborting the caller.
        return false;
    }
}
