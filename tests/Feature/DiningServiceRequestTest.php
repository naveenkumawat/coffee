<?php

namespace Tests\Feature;

use App\Enums\DiningServiceRequestStatus;
use App\Enums\DiningServiceRequestType;
use App\Enums\OperationalNotificationType;
use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Jobs\EscalateDiningServiceRequestJob;
use App\Models\CafeTable;
use App\Models\DiningServiceRequest;
use App\Models\DiningSession;
use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Dining\DiningServiceRequestService;
use App\Services\Dining\DiningServiceRequestServiceInterface;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DiningServiceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelBack();
    }

    public function test_customer_first_call_broadcasts_to_all_waiters_and_dedupes(): void
    {
        $this->enableDining();
        Queue::fake();

        $customer = User::factory()->customer()->create();
        $waiterA = User::factory()->waiter()->create();
        $waiterB = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W1', 'is_active' => true]);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($customer);

        $first = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))
            ->assertCreated()
            ->assertJsonPath('data.status', DiningServiceRequestStatus::Pending->value)
            ->assertJsonPath('data.type', DiningServiceRequestType::OrderAssistance->value);

        $requestId = (int) $first->json('data.id');

        $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))
            ->assertOk()
            ->assertJsonPath('data.id', $requestId);

        $this->assertSame(1, DiningServiceRequest::query()->count());
        $this->assertNull(DiningServiceRequest::query()->first()?->preferred_waiter_user_id);
        $this->assertNull(DiningServiceRequest::query()->first()?->escalated_at);

        Queue::assertNotPushed(EscalateDiningServiceRequestJob::class);

        $notification = OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningServiceRequested->value)
            ->first();
        $this->assertNotNull($notification);

        $recipientIds = OperationalNotificationRecipient::query()
            ->where('operational_notification_id', $notification->id)
            ->pluck('user_id')
            ->all();

        $this->assertContains($waiterA->id, $recipientIds);
        $this->assertContains($waiterB->id, $recipientIds);

        $this->getJson(route('api.v1.dining.sessions.show', $session))
            ->assertOk()
            ->assertJsonPath('data.service_request.id', $requestId)
            ->assertJsonPath('data.capabilities.can_call_waiter', true);
    }

    public function test_later_call_targets_latest_waiter_then_escalates(): void
    {
        $this->enableDining();
        Queue::fake();

        $customer = User::factory()->customer()->create();
        $waiterA = User::factory()->waiter()->create();
        $waiterB = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W2', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('5.00');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($waiterA);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiterA);
        $round1 = $dining->placeRound($session, $waiterA);
        $this->assertSame($waiterA->id, (int) $round1->placed_by_user_id);

        Sanctum::actingAs($customer);
        $response = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))
            ->assertCreated()
            ->assertJsonPath('data.preferred_waiter_user_id', $waiterA->id);

        $request = DiningServiceRequest::query()->findOrFail((int) $response->json('data.id'));
        $this->assertNull($request->escalated_at);

        Queue::assertPushed(EscalateDiningServiceRequestJob::class, function (EscalateDiningServiceRequestJob $job) use ($request): bool {
            return $job->diningServiceRequestId === (int) $request->id;
        });

        $preferredNotification = OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningServiceRequested->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($preferredNotification);
        $preferredRecipients = OperationalNotificationRecipient::query()
            ->where('operational_notification_id', $preferredNotification->id)
            ->pluck('user_id')
            ->all();
        $this->assertSame([$waiterA->id], $preferredRecipients);

        $this->travel(61)->seconds();
        app(DiningServiceRequestServiceInterface::class)->escalateIfDue($request->fresh());

        $request->refresh();
        $this->assertNotNull($request->escalated_at);

        $escalated = OperationalNotification::query()
            ->where('type', OperationalNotificationType::DiningServiceEscalated->value)
            ->latest('id')
            ->first();
        $this->assertNotNull($escalated);
        $escalatedRecipients = OperationalNotificationRecipient::query()
            ->where('operational_notification_id', $escalated->id)
            ->pluck('user_id')
            ->all();
        $this->assertContains($waiterA->id, $escalatedRecipients);
        $this->assertContains($waiterB->id, $escalatedRecipients);

        // Idempotent re-run
        app(DiningServiceRequestServiceInterface::class)->escalateIfDue($request->fresh());
        $this->assertSame(
            1,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::DiningServiceEscalated->value)
                ->count(),
        );
    }

    public function test_customer_created_latest_round_does_not_set_preferred_waiter(): void
    {
        $this->enableDining();
        Queue::fake();

        $customer = User::factory()->customer()->create();
        User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W3', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('4.00');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($customer);
        $dining->addDraftItem($session, (int) $variant->id, 1, $customer);
        $round = $dining->placeRound($session, $customer);
        $this->assertSame($customer->id, (int) $round->placed_by_user_id);

        $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))
            ->assertCreated()
            ->assertJsonPath('data.preferred_waiter_user_id', null);

        Queue::assertNotPushed(EscalateDiningServiceRequestJob::class);
    }

    public function test_inactive_preferred_waiter_broadcasts_immediately(): void
    {
        $this->enableDining();
        Queue::fake();

        $customer = User::factory()->customer()->create();
        $waiter = User::factory()->waiter()->create(['is_active' => true]);
        User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W4', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('4.50');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($waiter);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);

        $waiter->forceFill(['is_active' => false])->save();

        Sanctum::actingAs($customer);
        $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))
            ->assertCreated()
            ->assertJsonPath('data.preferred_waiter_user_id', null);

        Queue::assertNotPushed(EscalateDiningServiceRequestJob::class);
    }

    public function test_claim_first_wins_and_blocks_escalation(): void
    {
        $this->enableDining();
        Queue::fake();

        $customer = User::factory()->customer()->create();
        $waiterA = User::factory()->waiter()->create();
        $waiterB = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W5', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('5.00');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($waiterA);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiterA);
        $dining->placeRound($session, $waiterA);

        Sanctum::actingAs($customer);
        $created = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))->assertCreated();
        $requestId = (int) $created->json('data.id');
        $this->assertSame($waiterA->id, (int) $created->json('data.preferred_waiter_user_id'));
        $this->assertNull(DiningServiceRequest::query()->findOrFail($requestId)->escalated_at);

        Sanctum::actingAs($waiterA);
        $this->postJson(route('api.v1.waiter.service-requests.claim', $requestId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningServiceRequestStatus::Claimed->value)
            ->assertJsonPath('data.claimed_by_user_id', $waiterA->id);

        Sanctum::actingAs($waiterB);
        $this->postJson(route('api.v1.waiter.service-requests.claim', $requestId))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['request']);

        $request = DiningServiceRequest::query()->findOrFail($requestId);
        $this->travel(DiningServiceRequestService::ESCALATION_SECONDS + 1)->seconds();
        app(DiningServiceRequestServiceInterface::class)->escalateIfDue($request);
        $request->refresh();
        $this->assertSame(DiningServiceRequestStatus::Claimed, $request->status);
        $this->assertNull($request->escalated_at);
        $this->assertSame(
            0,
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::DiningServiceEscalated->value)
                ->count(),
        );
    }

    public function test_multi_waiter_rounds_share_session_and_complete_service_request(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $waiterA = User::factory()->waiter()->create();
        $waiterB = User::factory()->waiter()->create();
        $waiterC = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W6', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('3.00');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($customer);
        $created = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))->assertCreated();
        $requestId = (int) $created->json('data.id');

        Sanctum::actingAs($waiterA);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $waiterA);
        $round1 = $dining->placeRound($session->fresh(), $waiterA);

        $this->assertSame(DiningServiceRequestStatus::Completed, DiningServiceRequest::query()->findOrFail($requestId)->status);
        $this->assertSame('waiter_round_submitted', DiningServiceRequest::query()->findOrFail($requestId)->completion_reason);

        Sanctum::actingAs($waiterB);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 2, $waiterB);
        $round2 = $dining->placeRound($session->fresh(), $waiterB);

        Sanctum::actingAs($customer);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $customer);
        $round3 = $dining->placeRound($session->fresh(), $customer);

        Sanctum::actingAs($waiterC);
        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $waiterC);
        $round4 = $dining->placeRound($session->fresh(), $waiterC);

        $this->assertSame($waiterA->id, (int) $round1->placed_by_user_id);
        $this->assertSame($waiterB->id, (int) $round2->placed_by_user_id);
        $this->assertSame($customer->id, (int) $round3->placed_by_user_id);
        $this->assertSame($waiterC->id, (int) $round4->placed_by_user_id);
        $this->assertSame(4, Order::query()->where('dining_session_id', $session->id)->count());
        $this->assertSame(
            [(int) $session->id],
            Order::query()->whereIn('id', [$round1->id, $round2->id, $round3->id, $round4->id])
                ->pluck('dining_session_id')
                ->unique()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
        );
        $this->assertNull($session->fresh()->getAttribute('assigned_waiter_id'));
    }

    public function test_customer_self_order_resolves_pending_assistance_request(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W7', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('2.50');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($customer);
        $created = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))->assertCreated();
        $requestId = (int) $created->json('data.id');

        $dining->addDraftItem($session->fresh(), (int) $variant->id, 1, $customer);
        $dining->placeRound($session->fresh(), $customer);

        $request = DiningServiceRequest::query()->findOrFail($requestId);
        $this->assertSame(DiningServiceRequestStatus::Completed, $request->status);
        $this->assertSame('customer_self_ordered', $request->completion_reason);
    }

    public function test_customer_can_cancel_pending_request_and_cannot_call_for_other_session(): void
    {
        $this->enableDining();

        $customerA = User::factory()->customer()->create();
        $customerB = User::factory()->customer()->create();
        User::factory()->waiter()->create();
        $tableA = CafeTable::factory()->create(['code' => 'W8', 'is_active' => true]);
        $tableB = CafeTable::factory()->create(['code' => 'W9', 'is_active' => true]);
        $sessionA = $this->startCustomerSession($customerA, $tableA);
        $sessionB = $this->startCustomerSession($customerB, $tableB);

        Sanctum::actingAs($customerA);
        $created = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $sessionA))->assertCreated();
        $requestId = (int) $created->json('data.id');

        $this->postJson(route('api.v1.dining.service-requests.cancel', $requestId))
            ->assertOk()
            ->assertJsonPath('data.status', DiningServiceRequestStatus::Cancelled->value);

        $this->postJson(route('api.v1.dining.sessions.service-requests.store', $sessionB))
            ->assertForbidden();
    }

    public function test_waiter_tables_include_service_request_badge_payload(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $waiter = User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W10', 'is_active' => true]);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($customer);
        $created = $this->postJson(route('api.v1.dining.sessions.service-requests.store', $session))->assertCreated();

        Sanctum::actingAs($waiter);
        $this->getJson(route('api.v1.waiter.tables.index'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $table->id,
            ]);

        $payload = collect($this->getJson(route('api.v1.waiter.tables.index'))->json('data'))
            ->firstWhere('id', $table->id);

        $this->assertSame((int) $created->json('data.id'), (int) data_get($payload, 'session.service_request.id'));
        $this->assertSame('pending', data_get($payload, 'session.service_request.status'));

        $this->getJson(route('api.v1.waiter.service-requests.index'))
            ->assertOk()
            ->assertJsonPath('data.pending_count', 1);
    }

    public function test_escalation_command_processes_due_preferred_requests(): void
    {
        $this->enableDining();

        $customer = User::factory()->customer()->create();
        $waiter = User::factory()->waiter()->create();
        User::factory()->waiter()->create();
        $table = CafeTable::factory()->create(['code' => 'W11', 'is_active' => true]);
        $variant = $this->makePurchasableVariant('6.00');
        $dining = app(DiningSessionServiceInterface::class);
        $session = $this->startCustomerSession($customer, $table);

        Sanctum::actingAs($waiter);
        $dining->addDraftItem($session, (int) $variant->id, 1, $waiter);
        $dining->placeRound($session, $waiter);

        $request = app(DiningServiceRequestServiceInterface::class)->createOrderAssistance($session->fresh(), $customer);
        $this->assertNull($request->escalated_at);

        $this->travel(DiningServiceRequestService::ESCALATION_SECONDS + 1)->seconds();
        $this->artisan('coffee:escalate-dining-service-requests')->assertSuccessful();

        $this->assertNotNull($request->fresh()->escalated_at);
    }

    protected function startCustomerSession(User $customer, CafeTable $table): DiningSession
    {
        return app(DiningSessionServiceInterface::class)->startSession($table, $customer, $customer);
    }

    protected function enableDining(): void
    {
        WebsiteSetting::query()->updateOrCreate(
            ['key' => WebsiteSettingKey::FulfilmentDineInEnabled->value],
            ['value' => '1'],
        );
    }

    protected function makePurchasableVariant(string $price, string $name = 'Item'): ProductVariant
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => $name,
            'is_active' => true,
        ]);

        return ProductVariant::factory()->withConsumableRecipe()->create([
            'product_id' => $product->id,
            'price' => $price,
            'serving_size_value' => 1,
            'serving_size_unit' => ProductServingUnit::Piece->value,
            'is_active' => true,
        ]);
    }
}
