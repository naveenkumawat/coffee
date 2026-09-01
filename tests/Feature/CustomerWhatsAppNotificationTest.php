<?php

namespace Tests\Feature;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\UserRole;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Jobs\SendCustomerWhatsAppMessage;
use App\Models\CustomerNotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\OrderCustomerNotification;
use App\Services\Notification\CustomerNotificationDispatcherInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\WhatsApp\MetaWhatsAppCloudProvider;
use App\Services\WhatsApp\WhatsAppTemplateMessage;
use App\Services\WhatsApp\WhatsAppTemplatePayloadFactory;
use App\Support\PhoneNumber;
use App\Transfers\Order\OrderStatusTransitionTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerWhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('coffee.pwa.url', 'https://app.example.test');
        config()->set('coffee.company.name', 'The88Coffees');
        config()->set('services.whatsapp.enabled', false);
    }

    public function test_disabled_whatsapp_makes_no_http_calls(): void
    {
        Notification::fake();
        Http::fake();
        Http::preventStrayRequests();

        $customer = User::factory()->customer()->create([
            'email' => 'wa-off@example.test',
            'phone' => '9876543210',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        OrderPlaced::dispatch($order);

        Http::assertNothingSent();
        $this->assertSame(
            0,
            CustomerNotificationLog::query()
                ->where('channel', CustomerNotificationChannel::Whatsapp)
                ->count(),
        );
        Notification::assertSentTo($customer, OrderCustomerNotification::class);
    }

    public function test_enabled_whatsapp_posts_template_to_meta_cloud_api(): void
    {
        Notification::fake();
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.TEST123']],
            ], 200),
        ]);

        $customer = User::factory()->customer()->create([
            'email' => 'wa-on@example.test',
            'phone' => '9876543210',
            'name' => 'Asha Guest',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        OrderPlaced::dispatch($order);

        Http::assertSent(function (Request $request) use ($order): bool {
            $this->assertSame('https://graph.facebook.com/v21.0/555111/messages', $request->url());
            $this->assertSame('Bearer test-access-token', $request->header('Authorization')[0] ?? null);

            $data = $request->data();
            $this->assertSame('whatsapp', $data['messaging_product']);
            $this->assertSame('919876543210', $data['to']);
            $this->assertSame('template', $data['type']);
            $this->assertSame('tpl_order_placed', $data['template']['name']);
            $this->assertSame('en', $data['template']['language']['code']);

            $bodyParams = collect($data['template']['components'] ?? [])
                ->firstWhere('type', 'body')['parameters'] ?? [];
            $texts = collect($bodyParams)->pluck('text')->all();

            $this->assertContains('Asha', $texts);
            $this->assertContains((string) $order->order_number, $texts);
            $this->assertContains(number_format((float) $order->total_amount, 2, '.', ''), $texts);
            $this->assertStringNotContainsString('recipe', strtolower(json_encode($data)));
            $this->assertStringNotContainsString('gross profit', strtolower(json_encode($data)));
            $this->assertStringNotContainsString('payment_proof', strtolower(json_encode($data)));

            return true;
        });

        $log = CustomerNotificationLog::query()
            ->where('unique_key', 'order_placed:'.$order->id)
            ->where('channel', CustomerNotificationChannel::Whatsapp)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame('wamid.TEST123', $log->provider_message_id);
        $this->assertSame('919876543210', $log->recipient_phone);
    }

    public function test_missing_phone_skips_whatsapp_but_email_still_sends(): void
    {
        Notification::fake();
        $this->enableWhatsApp();
        Http::fake();
        Http::preventStrayRequests();

        $customer = User::factory()->customer()->create([
            'email' => 'nophone@example.test',
            'phone' => null,
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => null,
            'pickup_phone' => null,
            'delivery_phone' => null,
        ]);

        OrderPlaced::dispatch($order);

        Http::assertNothingSent();
        Notification::assertSentTo($customer, OrderCustomerNotification::class);
        $this->assertSame(
            0,
            CustomerNotificationLog::query()
                ->where('channel', CustomerNotificationChannel::Whatsapp)
                ->count(),
        );
    }

    public function test_missing_config_skips_whatsapp_safely(): void
    {
        Notification::fake();
        $this->enableWhatsApp();
        config()->set('services.whatsapp.access_token', null);
        Http::fake();
        Http::preventStrayRequests();

        $customer = User::factory()->customer()->create([
            'email' => 'noconfig@example.test',
            'phone' => '9876543210',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        OrderPlaced::dispatch($order);

        Http::assertNothingSent();
        Notification::assertSentTo($customer, OrderCustomerNotification::class);

        $log = CustomerNotificationLog::query()
            ->where('channel', CustomerNotificationChannel::Whatsapp)
            ->where('unique_key', 'order_placed:'.$order->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('credentials', strtolower((string) $log->error_message));
    }

    public function test_ready_templates_differ_for_pickup_and_delivery(): void
    {
        $this->enableWhatsApp();
        $factory = app(WhatsAppTemplatePayloadFactory::class);

        $customer = User::factory()->customer()->create(['phone' => '9876543210']);
        $takeaway = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);
        $delivery = $this->makeOrder($customer, OrderFulfilmentMethod::Delivery, [
            'customer_phone' => '9876543210',
            'delivery_address' => "12 Brew Lane\nCity",
        ]);

        $this->assertSame('order_ready_pickup', $factory->templateKey(CustomerNotificationType::OrderReady, $takeaway));
        $this->assertSame('order_ready_delivery', $factory->templateKey(CustomerNotificationType::OrderReady, $delivery));

        $pickupMessage = $factory->make(CustomerNotificationType::OrderReady, $takeaway, '919876543210');
        $deliveryMessage = $factory->make(CustomerNotificationType::OrderReady, $delivery, '919876543210');

        $this->assertSame('tpl_ready_pickup', $pickupMessage?->templateName);
        $this->assertSame('tpl_ready_delivery', $deliveryMessage?->templateName);
        $this->assertContains('12 Brew Lane City', $deliveryMessage?->bodyParameters ?? []);
    }

    public function test_preparing_whatsapp_is_off_by_default(): void
    {
        $factory = app(WhatsAppTemplatePayloadFactory::class);
        $customer = User::factory()->customer()->create(['phone' => '9876543210']);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        $this->assertNull($factory->templateKey(CustomerNotificationType::OrderPreparing, $order));
    }

    public function test_password_types_are_not_whatsapp_eligible(): void
    {
        $factory = app(WhatsAppTemplatePayloadFactory::class);
        $customer = User::factory()->customer()->create(['phone' => '9876543210']);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway);

        $this->assertNull($factory->templateKey(CustomerNotificationType::Welcome, $order));
        $this->assertNull($factory->templateKey(CustomerNotificationType::PasswordReset, $order));
        $this->assertNull($factory->templateKey(CustomerNotificationType::PasswordChanged, $order));
    }

    public function test_whatsapp_and_email_idempotency_are_independent(): void
    {
        Notification::fake();
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.ONCE']],
            ], 200),
        ]);

        $customer = User::factory()->customer()->create([
            'email' => 'idem@example.test',
            'phone' => '9876543210',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        OrderPlaced::dispatch($order);
        OrderPlaced::dispatch($order);

        Notification::assertSentToTimes($customer, OrderCustomerNotification::class, 1);
        Http::assertSentCount(1);

        $this->assertSame(
            1,
            CustomerNotificationLog::query()
                ->where('unique_key', 'order_placed:'.$order->id)
                ->where('channel', CustomerNotificationChannel::Email)
                ->where('status', 'sent')
                ->count(),
        );
        $this->assertSame(
            1,
            CustomerNotificationLog::query()
                ->where('unique_key', 'order_placed:'.$order->id)
                ->where('channel', CustomerNotificationChannel::Whatsapp)
                ->where('status', 'sent')
                ->count(),
        );
    }

    public function test_meta_failure_does_not_block_email_or_order_transition(): void
    {
        Notification::fake();
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => [
                    'message' => 'Template name does not exist in the translation',
                    'code' => 132001,
                ],
            ], 400),
        ]);

        $customer = User::factory()->customer()->create([
            'email' => 'failwa@example.test',
            'phone' => '9876543210',
        ]);
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
        ]);

        $updated = app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame(OrderStatus::Accepted, $updated->status);
        Notification::assertSentTo(
            $customer,
            OrderCustomerNotification::class,
            fn (OrderCustomerNotification $notification): bool => $notification->type === CustomerNotificationType::OrderAccepted,
        );

        $waLog = CustomerNotificationLog::query()
            ->where('channel', CustomerNotificationChannel::Whatsapp)
            ->where('type', CustomerNotificationType::OrderAccepted)
            ->first();

        $this->assertNotNull($waLog);
        $this->assertSame('failed', $waLog->status);
        $this->assertStringNotContainsString('test-access-token', (string) $waLog->error_message);
    }

    public function test_payment_proof_received_whatsapp_is_not_payment_confirmed_template(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.PROOF']],
            ], 200),
        ]);

        $customer = User::factory()->customer()->create([
            'email' => 'proofwa@example.test',
            'phone' => '9876543210',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        app(OrderServiceInterface::class)->uploadPaymentProof(
            $order,
            $customer,
            UploadedFile::fake()->image('proof.jpg', 200, 200),
        );

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return ($data['template']['name'] ?? null) === 'tpl_payment_proof_received';
        });

        $payload = json_encode(Http::recorded()[0][0]->data());
        $this->assertStringNotContainsString('payment confirmed', strtolower((string) $payload));
        $this->assertStringNotContainsString('proof.jpg', strtolower((string) $payload));
    }

    public function test_phone_normalization_for_meta_destination(): void
    {
        $this->assertSame('919876543210', PhoneNumber::toWhatsappDestination('9876543210'));
        $this->assertSame('919876543210', PhoneNumber::toWhatsappDestination('+91 98765 43210'));
        $this->assertSame('919876543210', PhoneNumber::toWhatsappDestination('919876543210'));
        $this->assertSame('447911123456', PhoneNumber::toWhatsappDestination('+44 7911 123456'));
        $this->assertNull(PhoneNumber::toWhatsappDestination(null));
        $this->assertNull(PhoneNumber::toWhatsappDestination(''));
    }

    public function test_provider_marks_connection_errors_retryable(): void
    {
        $this->enableWhatsApp();
        Http::fake([
            'graph.facebook.com/*' => Http::failedConnection(),
        ]);

        $result = app(MetaWhatsAppCloudProvider::class)->sendTemplate(new WhatsAppTemplateMessage(
            to: '919876543210',
            templateName: 'tpl_order_placed',
            language: 'en',
            bodyParameters: ['Asha', 'CC-1'],
        ));

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function test_whatsapp_job_is_queued_when_queue_is_not_sync(): void
    {
        Notification::fake();
        Queue::fake();
        $this->enableWhatsApp();

        $customer = User::factory()->customer()->create([
            'email' => 'queued@example.test',
            'phone' => '9876543210',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        OrderPlaced::dispatch($order);

        Queue::assertPushed(SendCustomerWhatsAppMessage::class);
    }

    public function test_email_dispatcher_failure_stub_does_not_abort_status_change(): void
    {
        $customer = User::factory()->customer()->create([
            'email' => 'stub@example.test',
            'phone' => '9876543210',
        ]);
        $admin = User::factory()->create(['role' => UserRole::Owner]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
            'status' => OrderStatus::PaymentConfirmed,
            'payment_status' => PaymentStatus::Confirmed,
        ]);

        $this->app->bind(CustomerNotificationDispatcherInterface::class, CustomerWhatsAppDispatcherThrowingStub::class);

        $updated = app(OrderServiceInterface::class)->transition(
            $order,
            $admin,
            $this->statusTransfer(OrderStatus::Accepted),
        );

        $this->assertSame(OrderStatus::Accepted, $updated->status);
    }

    public function test_status_lifecycle_whatsapp_events_use_mapped_templates(): void
    {
        Notification::fake();
        $this->enableWhatsApp();

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.STATUS']],
            ], 200),
        ]);

        $customer = User::factory()->customer()->create([
            'email' => 'lifecycle@example.test',
            'phone' => '9876543210',
        ]);
        $order = $this->makeOrder($customer, OrderFulfilmentMethod::Takeaway, [
            'customer_phone' => '9876543210',
        ]);

        $expected = [
            [OrderStatus::PaymentConfirmed, 'tpl_payment_confirmed'],
            [OrderStatus::Accepted, 'tpl_order_accepted'],
            [OrderStatus::ReadyForPickup, 'tpl_ready_pickup'],
            [OrderStatus::Completed, 'tpl_order_completed'],
            [OrderStatus::Cancelled, 'tpl_order_cancelled'],
        ];

        $from = OrderStatus::PendingPayment;

        foreach ($expected as [$to, $template]) {
            OrderStatusChanged::dispatch($order, $from, $to);
            $from = $to;
        }

        $names = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0]->data()['template']['name'] ?? null)
            ->filter()
            ->values()
            ->all();

        foreach ($expected as [, $template]) {
            $this->assertContains($template, $names);
        }
    }

    /**
     * @param  array<string, string|null>  $templateOverrides
     */
    protected function enableWhatsApp(array $templateOverrides = []): void
    {
        config()->set('services.whatsapp.enabled', true);
        config()->set('services.whatsapp.api_version', 'v21.0');
        config()->set('services.whatsapp.phone_number_id', '555111');
        config()->set('services.whatsapp.access_token', 'test-access-token');
        config()->set('services.whatsapp.language', 'en');
        config()->set('services.whatsapp.send_preparing', false);
        config()->set('services.whatsapp.templates', array_merge([
            'order_placed' => 'tpl_order_placed',
            'payment_proof_received' => 'tpl_payment_proof_received',
            'payment_confirmed' => 'tpl_payment_confirmed',
            'payment_proof_rejected' => 'tpl_payment_proof_rejected',
            'order_accepted' => 'tpl_order_accepted',
            'order_preparing' => 'tpl_order_preparing',
            'order_ready_pickup' => 'tpl_ready_pickup',
            'order_ready_delivery' => 'tpl_ready_delivery',
            'order_completed' => 'tpl_order_completed',
            'order_cancelled' => 'tpl_order_cancelled',
        ], $templateOverrides));
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

class CustomerWhatsAppDispatcherThrowingStub implements CustomerNotificationDispatcherInterface
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
        return false;
    }
}
