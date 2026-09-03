<?php

namespace Tests\Feature;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\Order\OrderStatusChanged;
use App\Models\CustomerBehaviourEvent;
use App\Models\CustomerVisitorIdentity;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Behaviour\BehaviourEventServiceInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerBehaviourEventApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_ingest_product_viewed_event(): void
    {
        $product = $this->makePublicProduct('Tracked Latte');
        $visitorKey = 'guestvisitor'.Str::lower(Str::random(16));

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'visitor_key' => $visitorKey,
            'product_id' => $product->id,
            'page_context' => '/menu/'.$product->id,
            'metadata' => ['source' => 'product_detail'],
        ])
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $this->assertDatabaseHas('customer_behaviour_events', [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => $visitorKey,
            'product_id' => $product->id,
            'customer_id' => null,
        ]);
    }

    public function test_authenticated_customer_event_attaches_customer_id(): void
    {
        $customer = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Auth View Drink');
        $visitorKey = 'authvisitor'.Str::lower(Str::random(16));

        $this->actingAs($customer, 'web')
            ->postJson(route('api.v1.behaviour.events.store'), [
                'event_type' => BehaviourEventType::FavouriteAdded->value,
                'visitor_key' => $visitorKey,
                'product_id' => $product->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $this->assertDatabaseHas('customer_behaviour_events', [
            'event_type' => BehaviourEventType::FavouriteAdded->value,
            'customer_id' => $customer->id,
            'visitor_key' => $visitorKey,
            'product_id' => $product->id,
        ]);
    }

    public function test_rejects_unsupported_and_server_only_event_types(): void
    {
        $visitorKey = 'rejectvisitor'.Str::lower(Str::random(12));

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => 'not_a_real_event',
            'visitor_key' => $visitorKey,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::OrderCompleted->value,
            'visitor_key' => $visitorKey,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::CampaignConverted->value,
            'visitor_key' => $visitorKey,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['event_type']);
    }

    public function test_rejects_invalid_product_reference_and_oversized_metadata(): void
    {
        $visitorKey = 'meta'.Str::lower(Str::random(12));

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'visitor_key' => $visitorKey,
            'product_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);

        config()->set('coffee.behaviour.metadata_max_bytes', 64);
        $service = app(BehaviourEventServiceInterface::class);

        try {
            $service->ingestClientEvent([
                'event_type' => BehaviourEventType::SearchPerformed->value,
                'visitor_key' => $visitorKey,
                'metadata' => [
                    'query' => str_repeat('ab', 40),
                ],
            ]);
            $this->fail('Expected ValidationException for oversized metadata.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('metadata', $exception->errors());
        }
    }

    public function test_search_query_is_normalized_and_length_limited(): void
    {
        config()->set('coffee.behaviour.search_query_max_length', 20);
        $visitorKey = 'search'.Str::lower(Str::random(12));

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::SearchPerformed->value,
            'visitor_key' => $visitorKey,
            'metadata' => [
                'query' => '  Vanilla   LATTE  Extra  ',
                'result_count' => 3,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $event = CustomerBehaviourEvent::query()->firstOrFail();
        $this->assertSame('vanilla latte extra', $event->metadata['query']);
        $this->assertSame(3, $event->metadata['result_count']);
    }

    public function test_tracking_disabled_accepts_without_persisting(): void
    {
        config()->set('coffee.behaviour.enabled', false);
        $product = $this->makePublicProduct('Disabled Track');

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'visitor_key' => 'disabled'.Str::lower(Str::random(10)),
            'product_id' => $product->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.accepted', false)
            ->assertJsonPath('data.reason', 'disabled');

        $this->assertDatabaseCount('customer_behaviour_events', 0);
    }

    public function test_content_exposes_tracking_enabled_flag(): void
    {
        config()->set('coffee.behaviour.enabled', false);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.behaviour.tracking_enabled', false);
    }

    public function test_anonymous_to_authenticated_merge_is_idempotent_and_blocks_foreign_claim(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        $product = $this->makePublicProduct('Merge Drink');
        $visitorKey = 'merge'.Str::lower(Str::random(16));

        $this->postJson(route('api.v1.behaviour.events.store'), [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'visitor_key' => $visitorKey,
            'product_id' => $product->id,
        ])->assertOk();

        $this->be($customer, 'web');
        $this->be($customer, 'sanctum');

        $this->postJson(route('api.v1.behaviour.merge'), [
            'visitor_key' => $visitorKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.merged', true)
            ->assertJsonPath('data.attached', 1);

        $this->assertDatabaseHas('customer_behaviour_events', [
            'visitor_key' => $visitorKey,
            'customer_id' => $customer->id,
        ]);
        $this->assertDatabaseHas('customer_visitor_identities', [
            'visitor_key' => $visitorKey,
            'customer_id' => $customer->id,
        ]);

        $this->postJson(route('api.v1.behaviour.merge'), [
            'visitor_key' => $visitorKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.merged', true)
            ->assertJsonPath('data.attached', 0);

        $this->be($other, 'web');
        $this->be($other, 'sanctum');

        $response = $this->postJson(route('api.v1.behaviour.merge'), [
            'visitor_key' => $visitorKey,
        ]);

        $response->assertOk();
        $this->assertFalse($response->json('data.merged'));
        $this->assertSame('visitor_claimed', $response->json('data.reason'));

        $this->assertSame(1, CustomerVisitorIdentity::query()->where('visitor_key', $visitorKey)->count());
        $this->assertSame(
            $customer->id,
            (int) CustomerBehaviourEvent::query()->where('visitor_key', $visitorKey)->value('customer_id'),
        );
    }

    public function test_merge_requires_customer_role(): void
    {
        $barista = User::factory()->create(['role' => UserRole::Barista]);

        $this->actingAs($barista, 'web')
            ->postJson(route('api.v1.behaviour.merge'), [
                'visitor_key' => 'staff'.Str::lower(Str::random(10)),
            ])
            ->assertForbidden();
    }

    public function test_server_authoritative_order_completed_is_idempotent(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::ReadyForPickup,
            'completed_at' => null,
        ]);

        event(new OrderStatusChanged(
            $order,
            OrderStatus::ReadyForPickup,
            OrderStatus::Completed,
        ));

        $this->assertDatabaseCount('customer_behaviour_events', 1);
        $this->assertDatabaseHas('customer_behaviour_events', [
            'event_type' => BehaviourEventType::OrderCompleted->value,
            'source' => BehaviourEventSource::Server->value,
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'idempotency_key' => 'server:order_completed:'.$order->id,
        ]);

        event(new OrderStatusChanged(
            $order,
            OrderStatus::ReadyForPickup,
            OrderStatus::Completed,
        ));

        $this->assertDatabaseCount('customer_behaviour_events', 1);
    }

    public function test_client_cannot_forge_order_completed_even_when_enum_bypassed_in_service(): void
    {
        $service = app(BehaviourEventServiceInterface::class);

        $this->expectException(ValidationException::class);

        $service->ingestClientEvent([
            'event_type' => BehaviourEventType::OrderCompleted->value,
            'visitor_key' => 'forge'.Str::lower(Str::random(10)),
        ]);
    }

    public function test_retention_prune_deletes_only_old_behaviour_events(): void
    {
        $product = $this->makePublicProduct('Prune Drink');
        $old = CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => 'oldvisitor1',
            'product_id' => $product->id,
            'occurred_at' => now()->subDays(200),
        ]);
        $fresh = CustomerBehaviourEvent::query()->create([
            'event_type' => BehaviourEventType::ProductViewed->value,
            'source' => BehaviourEventSource::Client->value,
            'visitor_key' => 'newvisitor1',
            'product_id' => $product->id,
            'occurred_at' => now()->subDay(),
        ]);
        $order = Order::factory()->create([
            'customer_id' => User::factory()->customer()->create()->id,
        ]);

        config()->set('coffee.behaviour.retention_days', 180);

        $this->artisan('coffee:behaviour-events-prune')
            ->assertSuccessful();

        $this->assertDatabaseMissing('customer_behaviour_events', ['id' => $old->id]);
        $this->assertDatabaseHas('customer_behaviour_events', ['id' => $fresh->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_behaviour_events_are_rate_limited(): void
    {
        RateLimiter::for('behaviour-events', function (Request $request) {
            $visitor = (string) $request->input('visitor_key', '');

            return Limit::perMinute(3)->by('test-behaviour:'.$visitor);
        });

        $product = $this->makePublicProduct('Rate Limit Drink');
        $visitorKey = 'rate'.Str::lower(Str::random(12));
        $payload = [
            'event_type' => BehaviourEventType::ProductViewed->value,
            'visitor_key' => $visitorKey,
            'product_id' => $product->id,
        ];

        $this->postJson(route('api.v1.behaviour.events.store'), $payload)->assertOk();
        $this->postJson(route('api.v1.behaviour.events.store'), $payload)->assertOk();
        $this->postJson(route('api.v1.behaviour.events.store'), $payload)->assertOk();
        $this->postJson(route('api.v1.behaviour.events.store'), $payload)->assertStatus(429);
    }

    protected function makePublicProduct(string $name): Product
    {
        $category = ProductCategory::factory()->create(['is_active' => true]);
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
            'is_available' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_active' => true,
            'is_available' => true,
            'price' => 120,
        ]);

        return $product->fresh(['category']);
    }
}
