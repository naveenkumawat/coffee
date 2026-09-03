<?php

namespace Tests\Feature;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\RebuildPersonalisationProfileJob;
use App\Models\AddOn;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAddOn;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalisationProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_cold_start_profile_has_insufficient_evidence(): void
    {
        $customer = User::factory()->customer()->create();
        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->assertFalse($profile->has_sufficient_evidence);
        $this->assertSame(0, $profile->event_sample_count);
        $this->assertSame(0, $profile->order_sample_count);
        $this->assertSame([], $profile->product_affinities);
        $this->assertSame([], $profile->category_affinities);
        $this->assertFalse($profile->spend_band['sufficient']);
        $this->assertNull($profile->spend_band['band']);
    }

    public function test_purchase_outweighs_favourite_cart_and_view(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);

        $customer = User::factory()->customer()->create();
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $purchased = $this->makeProduct('Purchased Brew', $category);
        $favourited = $this->makeProduct('Favourite Brew', $category);
        $carted = $this->makeProduct('Cart Brew', $category);
        $viewed = $this->makeProduct('Viewed Brew', $category);

        $this->createCompletedOrder($customer, $purchased, '250.00');
        $this->recordEvent($customer, null, BehaviourEventType::FavouriteAdded, $favourited);
        $this->recordEvent($customer, null, BehaviourEventType::CartItemAdded, $carted);
        $this->recordEvent($customer, null, BehaviourEventType::ProductViewed, $viewed);

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);
        $rankedIds = array_column($profile->product_affinities, 'id');

        $this->assertSame($purchased->id, $rankedIds[0]);
        $this->assertContains($favourited->id, $rankedIds);
        $this->assertTrue(
            $this->scoreFor($profile->product_affinities, $purchased->id)
            > $this->scoreFor($profile->product_affinities, $favourited->id)
        );
        $this->assertTrue(
            $this->scoreFor($profile->product_affinities, $favourited->id)
            > $this->scoreFor($profile->product_affinities, $carted->id)
        );
        $this->assertTrue(
            $this->scoreFor($profile->product_affinities, $carted->id)
            > $this->scoreFor($profile->product_affinities, $viewed->id)
        );
    }

    public function test_repeated_views_are_capped_with_diminishing_returns(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.max_repeats_per_signal', 3);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);
        config()->set('coffee.behaviour.profile.weights.product_viewed', 1.0);

        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct('Repeat View');

        for ($i = 0; $i < 10; $i++) {
            $this->recordEvent($customer, null, BehaviourEventType::ProductViewed, $product, occurredAt: now()->subMinutes($i));
        }

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);
        // 1 + 1/2 + 1/3 = 1.833... (occurrences beyond 3 contribute 0)
        $this->assertEqualsWithDelta(1.8333, $this->scoreFor($profile->product_affinities, $product->id), 0.01);
    }

    public function test_recency_weighting_prefers_recent_activity(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 30);

        $customer = User::factory()->customer()->create();
        $old = $this->makeProduct('Old View');
        $recent = $this->makeProduct('Recent View');

        $this->recordEvent($customer, null, BehaviourEventType::ProductViewed, $old, occurredAt: now()->subDays(90));
        $this->recordEvent($customer, null, BehaviourEventType::ProductViewed, $recent, occurredAt: now()->subHour());

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->assertTrue(
            $this->scoreFor($profile->product_affinities, $recent->id)
            > $this->scoreFor($profile->product_affinities, $old->id)
        );
    }

    public function test_category_variant_addon_and_flavour_preferences(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);

        $customer = User::factory()->customer()->create();
        $category = ProductCategory::factory()->create(['is_active' => true, 'name' => 'Espresso']);
        $flavour = ProductFlavour::factory()->create(['name' => 'Vanilla']);
        $product = $this->makeProduct('Vanilla Latte', $category);
        $product->flavours()->attach($flavour->id);
        $variant = $product->variants()->first();

        $order = $this->createCompletedOrder($customer, $product, '180.00', quantity: 2);
        $addOn = AddOn::factory()->create(['name' => 'Extra Shot', 'is_active' => true]);
        OrderItemAddOn::query()->create([
            'order_item_id' => $order->items()->first()->id,
            'add_on_id' => $addOn->id,
            'name' => 'Extra Shot',
            'quantity' => 1,
            'unit_price' => '20.00',
            'total_price' => '20.00',
        ]);

        $this->recordEvent($customer, null, BehaviourEventType::CategoryViewed, null, $category);
        $this->recordEvent(
            $customer,
            null,
            BehaviourEventType::ProductCustomized,
            $product,
            metadata: ['variant_id' => $variant->id, 'addon_ids' => [$addOn->id], 'addon_count' => 1],
            variantId: $variant->id,
        );

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->assertSame($category->id, $profile->category_affinities[0]['id']);
        $this->assertSame($product->id, $profile->product_affinities[0]['id']);
        $this->assertSame($flavour->id, $profile->flavour_affinities[0]['id']);
        $this->assertSame($variant->id, $profile->preferred_variants[0]['id']);
        $this->assertSame($addOn->id, $profile->addon_preferences[0]['id']);
        $this->assertContains($product->id, $profile->repeat_purchase_product_ids);
    }

    public function test_spend_band_and_time_of_day_from_completed_orders(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.min_orders_for_spend_band', 2);
        config()->set('coffee.timezone', 'Asia/Kolkata');

        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct('Band Drink');

        $morning = Carbon::create(2026, 6, 15, 9, 0, 0, 'Asia/Kolkata');
        $this->createCompletedOrder(
            $customer,
            $product,
            '150.00',
            completedAt: $morning->copy()->timezone(config('app.timezone')),
        );
        $this->createCompletedOrder(
            $customer,
            $product,
            '170.00',
            completedAt: $morning->copy()->addHour()->timezone(config('app.timezone')),
        );

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->assertTrue($profile->spend_band['sufficient']);
        $this->assertSame('low', $profile->spend_band['band']);
        $this->assertSame('morning', $profile->time_of_day_preferences[0]['period']);
        $this->assertTrue($profile->purchase_frequency['sufficient']);
    }

    public function test_cancelled_and_rejected_orders_are_excluded(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $kept = $this->makeProduct('Kept');
        $cancelled = $this->makeProduct('Cancelled');

        $this->createCompletedOrder($customer, $kept, '120.00');
        $this->createOrderWithStatus($customer, $cancelled, OrderStatus::Cancelled, '999.00');
        $this->createOrderWithStatus($customer, $cancelled, OrderStatus::Rejected, '999.00');

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);
        $ids = array_column($profile->product_affinities, 'id');

        $this->assertSame([$kept->id], $ids);
        $this->assertSame(1, $profile->order_sample_count);
    }

    public function test_visitor_profile_and_merge_into_customer(): void
    {
        Queue::fake();
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);

        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct('Guest Latte');
        $visitorKey = 'visitor'.Str::lower(Str::random(12));

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'visitor_key' => $visitorKey,
            'product_id' => $product->id,
        ])->assertOk();

        $visitorProfile = app(PersonalisationProfileServiceInterface::class)->rebuildForVisitor($visitorKey);
        $this->assertSame($visitorKey, $visitorProfile->visitor_key);
        $this->assertNull($visitorProfile->customer_id);
        $this->assertSame($product->id, $visitorProfile->product_affinities[0]['id']);

        $this->be($customer, 'web');
        $this->be($customer, 'sanctum');
        $this->postJson(route('api.v1.behaviour.merge'), [
            'visitor_key' => $visitorKey,
        ])->assertOk()->assertJsonPath('data.merged', true);

        Queue::assertPushed(RebuildPersonalisationProfileJob::class);

        $customerProfile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);
        $this->assertSame($product->id, $customerProfile->product_affinities[0]['id']);
        $this->assertNull(app(PersonalisationProfileServiceInterface::class)->getForVisitor($visitorKey));

        // Idempotent second merge + rebuild
        $this->postJson(route('api.v1.behaviour.merge'), [
            'visitor_key' => $visitorKey,
        ])->assertOk()->assertJsonPath('data.merged', true);

        $again = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);
        $this->assertEquals(
            $customerProfile->product_affinities,
            $again->product_affinities,
        );
    }

    public function test_deterministic_rebuild_and_engine_payload_contract(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct('Stable');
        $this->recordEvent($customer, null, BehaviourEventType::FavouriteAdded, $product);

        $service = app(PersonalisationProfileServiceInterface::class);
        $first = $service->rebuildForCustomer($customer->id);
        $second = $service->rebuildForCustomer($customer->id);

        $this->assertEquals($first->product_affinities, $second->product_affinities);
        $this->assertEquals($first->signals_meta['algorithm'], $second->signals_meta['algorithm']);

        $payload = $service->profilePayloadForCustomer($customer->id);
        $this->assertSame($customer->id, $payload['customer_id']);
        $this->assertArrayHasKey('product_affinities', $payload);
        $this->assertArrayHasKey('has_sufficient_evidence', $payload);
        $this->assertArrayNotHasKey('metadata', $payload);
    }

    public function test_tracking_disabled_skips_behaviour_but_keeps_order_derivation(): void
    {
        config()->set('coffee.behaviour.enabled', false);
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);

        $customer = User::factory()->customer()->create();
        $viewed = $this->makeProduct('Should Ignore');
        $purchased = $this->makeProduct('Still Counts');

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer->id,
            'visitor_key' => 'x',
            'product_id' => $viewed->id,
            'occurred_at' => now(),
        ]);
        $this->createCompletedOrder($customer, $purchased, '140.00');

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->assertSame(0, $profile->event_sample_count);
        $this->assertSame(1, $profile->order_sample_count);
        $this->assertSame([$purchased->id], array_column($profile->product_affinities, 'id'));
    }

    public function test_raw_event_pruning_does_not_break_profile_rebuild(): void
    {
        config()->set('coffee.behaviour.retention_days', 30);
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.lookback_days', 180);

        $customer = User::factory()->customer()->create();
        $oldProduct = $this->makeProduct('Old Signal');
        $freshProduct = $this->makeProduct('Fresh Signal');

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer->id,
            'visitor_key' => 'prune1',
            'product_id' => $oldProduct->id,
            'occurred_at' => now()->subDays(60),
        ]);
        $this->recordEvent($customer, null, BehaviourEventType::FavouriteAdded, $freshProduct);

        $this->artisan('coffee:behaviour-events-prune')->assertSuccessful();

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);
        $ids = array_column($profile->product_affinities, 'id');

        $this->assertContains($freshProduct->id, $ids);
        $this->assertNotContains($oldProduct->id, $ids);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_profile_reset_does_not_delete_orders(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct('Reset Me');
        $this->createCompletedOrder($customer, $product, '100.00');
        app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        $this->assertDatabaseCount('personalisation_profiles', 1);

        $this->artisan('coffee:personalisation-profiles-rebuild', [
            '--reset-customer' => $customer->id,
        ])->assertSuccessful();

        $this->assertDatabaseCount('personalisation_profiles', 0);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_does_not_double_count_behaviour_order_completed_events(): void
    {
        config()->set('coffee.behaviour.profile.min_evidence_signals', 1);
        config()->set('coffee.behaviour.profile.recency_half_life_days', 3650);
        config()->set('coffee.behaviour.profile.weights.purchase_item', 10.0);

        $customer = User::factory()->customer()->create();
        $product = $this->makeProduct('No Double');
        $order = $this->createCompletedOrder($customer, $product, '100.00');

        CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::OrderCompleted->value,
            'source' => BehaviourEventSource::Server->value,
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'occurred_at' => now(),
            'idempotency_key' => 'server:order_completed:'.$order->id,
        ]);

        $profile = app(PersonalisationProfileServiceInterface::class)->rebuildForCustomer($customer->id);

        // One purchase line qty 1 => score ~10, not 20
        $this->assertEqualsWithDelta(10.0, $this->scoreFor($profile->product_affinities, $product->id), 0.05);
    }

    protected function scoreFor(array $ranked, int $id): float
    {
        foreach ($ranked as $row) {
            if ((int) $row['id'] === $id) {
                return (float) $row['score'];
            }
        }

        return 0.0;
    }

    protected function makeProduct(string $name, ?ProductCategory $category = null): Product
    {
        $category ??= ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Regular',
            'price' => 120,
            'is_active' => true,
            'is_available' => true,
        ]);

        return $product->fresh(['category', 'variants']);
    }

    protected function recordEvent(
        ?User $customer,
        ?string $visitorKey,
        BehaviourEventType $type,
        ?Product $product = null,
        ?ProductCategory $category = null,
        ?Carbon $occurredAt = null,
        array $metadata = [],
        ?int $variantId = null,
    ): CustomerBehaviourEvent {
        return CustomerBehaviourEvent::query()->create([
            'event_type' => $type->value,
            'source' => BehaviourEventSource::Client->value,
            'customer_id' => $customer?->id,
            'visitor_key' => $visitorKey ?? 'test'.Str::lower(Str::random(8)),
            'product_id' => $product?->id,
            'product_category_id' => $category?->id ?? $product?->product_category_id,
            'product_variant_id' => $variantId,
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }

    protected function createCompletedOrder(
        User $customer,
        Product $product,
        string $total,
        int $quantity = 1,
        ?Carbon $completedAt = null,
    ): Order {
        return $this->createOrderWithStatus(
            $customer,
            $product,
            OrderStatus::Completed,
            $total,
            $quantity,
            $completedAt ?? now(),
        );
    }

    protected function createOrderWithStatus(
        User $customer,
        Product $product,
        OrderStatus $status,
        string $total,
        int $quantity = 1,
        ?Carbon $completedAt = null,
    ): Order {
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'payment_status' => $status === OrderStatus::Completed
                ? PaymentStatus::Confirmed
                : PaymentStatus::Pending,
            'total_amount' => $total,
            'completed_at' => $status === OrderStatus::Completed ? ($completedAt ?? now()) : null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants()->first()?->id,
            'product_name' => $product->name,
            'variant_name' => 'Regular',
            'unit_price' => $total,
            'quantity' => $quantity,
            'line_subtotal' => $total,
        ]);

        return $order->fresh(['items.product', 'items.addOns']);
    }
}
