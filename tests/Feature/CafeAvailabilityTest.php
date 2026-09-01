<?php

namespace Tests\Feature;

use App\Enums\CafeClosureType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeClosure;
use App\Models\CafeOperatingHour;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CafeAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setTimezone('Asia/Kolkata');
        $this->seedStandardHours();
    }

    public function test_order_allowed_during_business_hours(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00', 'Asia/Kolkata'));

        $this->placeCheckout(User::factory()->customer()->create())->assertCreated();
    }

    public function test_order_rejected_before_opening_and_after_closing(): void
    {
        $customer = User::factory()->customer()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 07:59:00', 'Asia/Kolkata'));
        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cafe_closed');

        $this->travelTo(CarbonImmutable::parse('2026-09-01 22:00:00', 'Asia/Kolkata'));
        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cafe_closed');
    }

    public function test_closed_weekday_is_rejected(): void
    {
        CafeOperatingHour::query()->where('weekday', CarbonImmutable::parse('2026-09-06', 'Asia/Kolkata')->dayOfWeek)->delete();

        $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00', 'Asia/Kolkata'));

        $this->placeCheckout(User::factory()->customer()->create())
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cafe_closed');
    }

    public function test_active_holiday_and_hourly_closure_windows(): void
    {
        $customer = User::factory()->customer()->create();
        $timezone = 'Asia/Kolkata';

        CafeClosure::factory()->create([
            'title' => 'Diwali',
            'type' => CafeClosureType::Holiday,
            'starts_at' => CarbonImmutable::parse('2026-09-01 00:00:00', $timezone)->timezone('UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-01 23:59:59', $timezone)->timezone('UTC'),
            'customer_message' => 'Closed for Diwali.',
            'is_active' => true,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-01 11:00:00', $timezone));
        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cafe_closed')
            ->assertJsonFragment(['message' => 'Closed for Diwali.']);

        CafeClosure::query()->delete();

        CafeClosure::factory()->create([
            'title' => 'Maintenance',
            'type' => CafeClosureType::Maintenance,
            'starts_at' => CarbonImmutable::parse('2026-09-02 14:00:00', $timezone)->timezone('UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-02 17:00:00', $timezone)->timezone('UTC'),
            'customer_message' => 'Closed for maintenance.',
            'is_active' => true,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-09-02 15:00:00', $timezone));
        $this->placeCheckout($customer)->assertUnprocessable()->assertJsonPath('code', 'cafe_closed');

        $this->travelTo(CarbonImmutable::parse('2026-09-02 17:00:00', $timezone));
        $this->placeCheckout($customer)->assertCreated();
    }

    public function test_timed_and_indefinite_manual_closure(): void
    {
        $service = $this->app->make(CafeAvailabilityServiceInterface::class);
        $customer = User::factory()->customer()->create();
        $admin = User::factory()->manager()->create();

        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00', 'Asia/Kolkata'));

        $until = CarbonImmutable::parse('2026-09-01 16:30:00', 'Asia/Kolkata');
        $service->closeOrdering($until, 'Temporarily unavailable until 4:30 PM.');

        $this->placeCheckout($customer)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cafe_closed');

        $this->travelTo(CarbonImmutable::parse('2026-09-01 16:30:00', 'Asia/Kolkata'));
        $this->placeCheckout($customer)->assertCreated();

        $service->closeOrdering(null, 'Temporarily unavailable.');
        $this->placeCheckout($customer)->assertUnprocessable()->assertJsonPath('code', 'cafe_closed');

        $this->actingAs($admin, 'admin')
            ->post(route('administrator.cafe-schedule.reopen'))
            ->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->placeCheckout($customer->fresh())->assertCreated();
    }

    public function test_administrator_can_manage_schedule_barista_and_customer_cannot(): void
    {
        $admin = User::factory()->manager()->create();
        $barista = User::factory()->barista()->create();
        $customer = User::factory()->customer()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('administrator.cafe-schedule.index'))
            ->assertOk();

        $this->actingAs($admin, 'admin')
            ->put(route('administrator.cafe-schedule.hours.update'), [
                'days' => collect(range(0, 6))->mapWithKeys(fn (int $day): array => [
                    $day => ['enabled' => '1', 'opens_at' => '09:00', 'closes_at' => '21:00'],
                ])->all(),
            ])
            ->assertRedirect(route('administrator.cafe-schedule.index'));

        $this->assertSame(7, CafeOperatingHour::query()->count());

        $this->actingAs($barista, 'admin')
            ->put(route('administrator.cafe-schedule.hours.update'), [
                'days' => [1 => ['enabled' => '1', 'opens_at' => '10:00', 'closes_at' => '20:00']],
            ])
            ->assertForbidden();

        $this->actingAs($customer, 'web')
            ->post(route('administrator.cafe-schedule.close'), [
                'mode' => 'indefinite',
            ])
            ->assertForbidden();
    }

    public function test_existing_orders_and_cart_remain_when_closed(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ]);

        Sanctum::actingAs($customer);
        $variant = $this->makePurchasableVariant('8.00');
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->app->make(CafeAvailabilityServiceInterface::class)->closeOrdering();

        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->getJson(route('api.v1.cart.show'))->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->placeCheckout($customer)->assertUnprocessable()->assertJsonPath('code', 'cafe_closed');
        $this->getJson(route('api.v1.cart.show'))->assertOk()->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_public_availability_endpoint_and_content_exclude_internal_notes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00', 'Asia/Kolkata'));

        CafeClosure::factory()->create([
            'title' => 'Private event',
            'type' => CafeClosureType::PrivateEvent,
            'starts_at' => CarbonImmutable::parse('2026-09-01 09:00:00', 'Asia/Kolkata')->timezone('UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-01 18:00:00', 'Asia/Kolkata')->timezone('UTC'),
            'customer_message' => 'Private event this evening.',
            'internal_note' => 'SECRET STAFF NOTE',
            'is_active' => true,
        ]);

        Cache::flush();

        $response = $this->getJson(route('api.v1.cafe-availability.show'))
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.message', 'Private event this evening.');

        $this->assertStringNotContainsString('SECRET', (string) $response->getContent());

        $content = $this->getJson(route('api.v1.content.show'))->assertOk();
        $this->assertFalse($content->json('data.availability.available'));
        $this->assertStringNotContainsString('SECRET', (string) $content->getContent());
    }

    public function test_next_opening_accounts_for_closure_end(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 15:00:00', 'Asia/Kolkata'));

        CafeClosure::factory()->create([
            'type' => CafeClosureType::Maintenance,
            'starts_at' => CarbonImmutable::parse('2026-09-01 14:00:00', 'Asia/Kolkata')->timezone('UTC'),
            'ends_at' => CarbonImmutable::parse('2026-09-01 17:00:00', 'Asia/Kolkata')->timezone('UTC'),
            'is_active' => true,
        ]);

        $status = $this->app->make(CafeAvailabilityServiceInterface::class)->status();

        $this->assertFalse($status->available);
        $this->assertNotNull($status->nextOpenAt);
        $this->assertTrue(
            $status->nextOpenAt->equalTo(CarbonImmutable::parse('2026-09-01 17:00:00', 'Asia/Kolkata')),
        );
    }

    public function test_checkout_token_idempotency_still_returns_existing_order_when_closed(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01 10:00:00', 'Asia/Kolkata'));
        $customer = User::factory()->customer()->create([
            'name' => 'Idempotent Customer',
            'phone' => '9111222333',
        ]);

        Sanctum::actingAs($customer);
        $variant = $this->makePurchasableVariant('6.00');
        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();
        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');
        $payload = [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone,
        ];

        $first = $this->postJson(route('api.v1.checkout.store'), $payload)->assertCreated();
        $orderId = (int) $first->json('data.id');

        $this->app->make(CafeAvailabilityServiceInterface::class)->closeOrdering();

        $this->postJson(route('api.v1.checkout.store'), $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    }

    protected function seedStandardHours(): void
    {
        CafeOperatingHour::query()->delete();

        for ($weekday = 0; $weekday <= 6; $weekday++) {
            CafeOperatingHour::query()->create([
                'weekday' => $weekday,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
                'sort_order' => 0,
            ]);
        }
    }

    protected function setTimezone(string $timezone): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::BusinessTimezone->value],
            [
                'section' => WebsiteSettingKey::BusinessTimezone->section(),
                'value_type' => WebsiteSettingKey::BusinessTimezone->valueType(),
                'value' => $timezone,
            ],
        );
    }

    protected function placeCheckout(User $customer): TestResponse
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($customer);
        $variant = $this->makePurchasableVariant('9.00');

        $this->postJson(route('api.v1.cart.items.store'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])->assertCreated();

        $token = (string) $this->getJson(route('api.v1.checkout.summary'))->json('meta.checkout_token');

        return $this->postJson(route('api.v1.checkout.store'), [
            'checkout_token' => $token,
            'fulfilment_method' => 'takeaway',
            'payment_method' => 'manual_upi',
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone ?: '9000000000',
            'pickup_name' => $customer->name,
            'pickup_phone' => $customer->phone ?: '9000000000',
        ]);
    }

    protected function makePurchasableVariant(string $price): ProductVariant
    {
        $category = ProductCategory::factory()->create([
            'name' => fake()->unique()->words(2, true),
        ]);

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
            'is_available' => true,
        ]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'serving_size_value' => '300.000',
            'serving_size_unit' => ProductServingUnit::Milliliter,
            'price' => $price,
            'is_active' => true,
            'is_available' => true,
        ]);
    }
}
