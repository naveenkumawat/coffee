<?php

namespace Tests\Feature;

use App\Enums\IngredientUnit;
use App\Enums\InventoryTransactionType;
use App\Enums\OperationalNotificationType;
use App\Events\Realtime\InventoryOpsSignalBroadcasted;
use App\Models\Ingredient;
use App\Models\OperationalNotification;
use App\Models\User;
use App\Services\Inventory\InventoryRefillRequestServiceInterface;
use App\Services\Inventory\InventoryServiceInterface;
use App\Transfers\Inventory\InventoryRefillRequestTransferInterface;
use App\Transfers\Inventory\InventoryTransactionTransferInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OperationalInventoryRealtimeWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_notifies_admin_and_operator_not_waiter_and_dedupes_open_episode(): void
    {
        $owner = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $waiter = User::factory()->waiter()->create();
        $ingredient = $this->makeTrackedIngredient(current: '50.000', reorder: '30.000', minimum: '10.000');

        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '25.000', $owner);
        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '1.000', $owner);

        $notifications = OperationalNotification::query()
            ->where('type', OperationalNotificationType::InventoryStockLow->value)
            ->where('subject_id', $ingredient->id)
            ->get();

        $this->assertCount(1, $notifications);
        $this->assertFalse($notifications->first()->action_required);

        $ids = $notifications->first()->recipients->pluck('user_id')->all();
        $this->assertContains($owner->id, $ids);
        $this->assertContains($operator->id, $ids);
        $this->assertNotContains($waiter->id, $ids);
        $this->assertArrayNotHasKey('purchase_cost', $notifications->first()->metadata ?? []);
        $this->assertArrayNotHasKey('cost_per_unit', $notifications->first()->metadata ?? []);
    }

    public function test_out_of_stock_is_actionable_for_admin_operator_barista_and_resolves_on_restore(): void
    {
        $owner = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $ingredient = $this->makeTrackedIngredient(current: '50.000', reorder: '30.000', minimum: '10.000');

        $this->recordMovement($ingredient, InventoryTransactionType::ManualReduction, '50.000', $owner);

        $out = OperationalNotification::query()
            ->where('type', OperationalNotificationType::InventoryStockOut->value)
            ->first();

        $this->assertNotNull($out);
        $this->assertTrue($out->action_required);
        $ids = $out->recipients->pluck('user_id')->all();
        $this->assertContains($owner->id, $ids);
        $this->assertContains($operator->id, $ids);
        $this->assertContains($barista->id, $ids);

        $this->recordMovement($ingredient, InventoryTransactionType::StockAdded, '40.000', $owner);

        $this->assertNotNull($out->fresh()->resolved_at);
    }

    public function test_refill_requested_actionable_for_admin_operator_and_signal_is_safe(): void
    {
        Event::fake([InventoryOpsSignalBroadcasted::class]);

        $owner = User::factory()->owner()->create();
        $operator = User::factory()->operator()->create();
        $barista = User::factory()->barista()->create();
        $ingredient = $this->makeTrackedIngredient(current: '5.000', reorder: '30.000', minimum: '10.000');

        $this->createRefillRequest($barista, $ingredient, '20.000');

        $notification = OperationalNotification::query()
            ->where('type', OperationalNotificationType::InventoryRefillRequested->value)
            ->first();

        $this->assertNotNull($notification);
        $this->assertTrue($notification->action_required);
        $ids = $notification->recipients->pluck('user_id')->all();
        $this->assertContains($owner->id, $ids);
        $this->assertContains($operator->id, $ids);

        Event::assertDispatched(InventoryOpsSignalBroadcasted::class, function (InventoryOpsSignalBroadcasted $event): bool {
            $payload = $event->payload;
            $forbidden = ['purchase_cost', 'cost_per_unit', 'margin', 'profit', 'email'];
            foreach ($forbidden as $key) {
                if (array_key_exists($key, $payload)) {
                    return false;
                }
            }

            return ($payload['type'] ?? null) === 'refill.requested'
                && isset($payload['event_id'], $payload['entity_id']);
        });
    }

    public function test_refill_completed_resolves_open_refill_request_notification(): void
    {
        $owner = User::factory()->owner()->create();
        $barista = User::factory()->barista()->create();
        $ingredient = $this->makeTrackedIngredient(current: '5.000', reorder: '30.000', minimum: '10.000');
        $request = $this->createRefillRequest($barista, $ingredient, '20.000');

        $service = app(InventoryRefillRequestServiceInterface::class);
        $service->approve($request, $owner, 'OK');

        $this->recordMovement(
            $ingredient,
            InventoryTransactionType::StockAdded,
            '20.000',
            $owner,
            $request->id,
        );

        $open = OperationalNotification::query()
            ->where('type', OperationalNotificationType::InventoryRefillRequested->value)
            ->whereNull('resolved_at')
            ->count();

        $this->assertSame(0, $open);
        $this->assertTrue(
            OperationalNotification::query()
                ->where('type', OperationalNotificationType::InventoryRefillUpdated->value)
                ->where('idempotency_key', 'like', '%:completed')
                ->exists(),
        );
    }

    protected function makeTrackedIngredient(
        string $current,
        string $reorder,
        string $minimum,
        string $name = 'Milk',
    ): Ingredient {
        return Ingredient::factory()->create([
            'name' => $name,
            'measurement_unit' => IngredientUnit::Milliliter,
            'base_measurement_unit' => IngredientUnit::Milliliter,
            'current_stock' => $current,
            'reorder_level' => $reorder,
            'minimum_stock' => $minimum,
            'purchase_cost' => '999.00',
            'cost_per_unit' => '9.9900',
            'is_active' => true,
        ]);
    }

    protected function recordMovement(
        Ingredient $ingredient,
        InventoryTransactionType $type,
        string $quantity,
        ?User $actor = null,
        ?int $refillRequestId = null,
    ): void {
        $actor ??= User::factory()->owner()->create();

        $transfer = app(InventoryTransactionTransferInterface::class);
        $transfer->setIngredientId($ingredient->id);
        $transfer->setTransactionType($type->value);
        $transfer->setQuantity($quantity);
        $transfer->setMeasurementUnit(IngredientUnit::Milliliter->value);
        $transfer->setCreatedBy($actor->id);

        if ($refillRequestId !== null) {
            $transfer->setReferenceType('inventory_refill_request');
            $transfer->setReferenceId($refillRequestId);
        }

        app(InventoryServiceInterface::class)->recordTransaction($transfer);
    }

    protected function createRefillRequest(User $barista, Ingredient $ingredient, string $quantity)
    {
        $transfer = app(InventoryRefillRequestTransferInterface::class);
        $transfer->setIngredientId($ingredient->id);
        $transfer->setQuantity($quantity);
        $transfer->setMeasurementUnit(IngredientUnit::Milliliter->value);
        $transfer->setNotes('Need more soon');

        return app(InventoryRefillRequestServiceInterface::class)->store($barista, $transfer);
    }
}
