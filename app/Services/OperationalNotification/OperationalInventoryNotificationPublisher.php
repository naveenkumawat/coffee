<?php

namespace App\Services\OperationalNotification;

use App\Enums\InventoryRefillRequestStatus;
use App\Enums\InventoryStockStatus;
use App\Enums\OperationalNotificationPriority;
use App\Enums\OperationalNotificationType;
use App\Enums\UserRole;
use App\Events\Realtime\InventoryOpsSignalBroadcasted;
use App\Models\Ingredient;
use App\Models\InventoryRefillRequest;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * R1.5 inventory/refill → operational notifications + minimal role-channel signals.
 */
class OperationalInventoryNotificationPublisher
{
    public function __construct(
        protected OperationalNotificationServiceInterface $notifications,
    ) {}

    public function handleStockStatusChanged(
        Ingredient $ingredient,
        InventoryStockStatus $fromStatus,
        InventoryStockStatus $toStatus,
        InventoryTransaction $transaction,
    ): void {
        $this->safe(function () use ($ingredient, $fromStatus, $toStatus, $transaction): void {
            if ($fromStatus === $toStatus) {
                return;
            }

            $this->broadcastSignal(
                type: 'inventory.stock_state_changed',
                entity: 'ingredient',
                entityId: (int) $ingredient->getKey(),
                name: (string) $ingredient->name,
                state: $toStatus->value,
                channels: ['role.administrator', 'role.operator', 'role.barista'],
                eventId: 'stock:'.$ingredient->getKey().':'.$toStatus->value.':tx:'.$transaction->getKey(),
            );

            if ($toStatus === InventoryStockStatus::LowStock
                && $fromStatus === InventoryStockStatus::InStock) {
                $this->notifications->createUniqueAndBroadcast(
                    idempotencyKey: 'inventory.stock_low:Ingredient:'.$ingredient->getKey().':open',
                    type: OperationalNotificationType::InventoryStockLow->value,
                    category: 'inventory',
                    title: 'Low stock',
                    message: $ingredient->name.' is running low.',
                    audience: $this->adminOperatorAudience(),
                    priority: OperationalNotificationPriority::Normal,
                    actionRequired: false,
                    actionUrl: $this->inventoryUrl($ingredient),
                    subject: $ingredient,
                    metadata: $this->safeIngredientMeta($ingredient, $toStatus),
                );

                return;
            }

            if ($toStatus === InventoryStockStatus::OutOfStock) {
                $this->notifications->resolveOpenForSubject(
                    $ingredient,
                    [OperationalNotificationType::InventoryStockLow],
                    resolutionAction: 'out_of_stock',
                );

                $this->notifications->createUniqueAndBroadcast(
                    idempotencyKey: 'inventory.stock_out:Ingredient:'.$ingredient->getKey().':open',
                    type: OperationalNotificationType::InventoryStockOut->value,
                    category: 'inventory',
                    title: 'Out of stock',
                    message: $ingredient->name.' is out of stock.',
                    audience: [...$this->adminOperatorAudience(), UserRole::Barista],
                    priority: OperationalNotificationPriority::High,
                    actionRequired: true,
                    actionCode: 'restock',
                    actionUrl: $this->inventoryUrl($ingredient),
                    subject: $ingredient,
                    metadata: $this->safeIngredientMeta($ingredient, $toStatus),
                );

                return;
            }

            if ($toStatus === InventoryStockStatus::InStock
                && in_array($fromStatus, [InventoryStockStatus::LowStock, InventoryStockStatus::OutOfStock], true)) {
                $this->notifications->resolveOpenForSubject(
                    $ingredient,
                    [
                        OperationalNotificationType::InventoryStockLow,
                        OperationalNotificationType::InventoryStockOut,
                    ],
                    resolutionAction: 'stock_restored',
                );

                $this->notifications->createUniqueAndBroadcast(
                    idempotencyKey: 'inventory.stock_restored:Ingredient:'.$ingredient->getKey().':'.$transaction->getKey(),
                    type: OperationalNotificationType::InventoryStockRestored->value,
                    category: 'inventory',
                    title: 'Stock restored',
                    message: $ingredient->name.' is back in stock.',
                    audience: $this->adminOperatorAudience(),
                    priority: OperationalNotificationPriority::Normal,
                    actionRequired: false,
                    actionUrl: $this->inventoryUrl($ingredient),
                    subject: $ingredient,
                    metadata: $this->safeIngredientMeta($ingredient, $toStatus),
                );
            }
        });
    }

    public function handleRefillCreated(InventoryRefillRequest $request): void
    {
        $this->safe(function () use ($request): void {
            $request->loadMissing('ingredient');

            $this->broadcastSignal(
                type: 'refill.requested',
                entity: 'refill_request',
                entityId: (int) $request->getKey(),
                name: $request->ingredient?->name,
                state: InventoryRefillRequestStatus::Pending->value,
                channels: ['role.administrator', 'role.operator', 'role.barista'],
                eventId: 'refill:'.$request->getKey().':pending',
            );

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: 'inventory.refill_requested:InventoryRefillRequest:'.$request->getKey().':pending',
                type: OperationalNotificationType::InventoryRefillRequested->value,
                category: 'inventory',
                title: 'Refill requested',
                message: 'Refill requested for '.($request->ingredient?->name ?? 'ingredient').'.',
                audience: $this->adminOperatorAudience(),
                priority: OperationalNotificationPriority::High,
                actionRequired: true,
                actionCode: 'review_refill',
                actionUrl: $this->refillUrl($request),
                subject: $request,
                metadata: $this->safeRefillMeta($request),
            );
        });
    }

    public function handleRefillStatusChanged(
        InventoryRefillRequest $request,
        InventoryRefillRequestStatus $fromStatus,
        InventoryRefillRequestStatus $toStatus,
    ): void {
        $this->safe(function () use ($request, $fromStatus, $toStatus): void {
            if ($fromStatus === $toStatus) {
                return;
            }

            $request->loadMissing(['ingredient', 'requestedBy']);

            $signalType = match ($toStatus) {
                InventoryRefillRequestStatus::Completed => 'refill.completed',
                InventoryRefillRequestStatus::Rejected => 'refill.rejected',
                default => 'refill.updated',
            };

            $this->broadcastSignal(
                type: $signalType,
                entity: 'refill_request',
                entityId: (int) $request->getKey(),
                name: $request->ingredient?->name,
                state: $toStatus->value,
                channels: ['role.administrator', 'role.operator', 'role.barista'],
                eventId: 'refill:'.$request->getKey().':'.$toStatus->value,
            );

            if (in_array($toStatus, [
                InventoryRefillRequestStatus::Approved,
                InventoryRefillRequestStatus::Rejected,
                InventoryRefillRequestStatus::Completed,
            ], true)) {
                $this->notifications->resolveOpenForSubject(
                    $request,
                    [OperationalNotificationType::InventoryRefillRequested],
                    resolutionAction: $toStatus->value,
                );
            }

            if ($toStatus === InventoryRefillRequestStatus::Completed) {
                $ingredient = $request->ingredient;
                if ($ingredient instanceof Ingredient) {
                    $this->notifications->resolveOpenForSubject(
                        $ingredient,
                        [
                            OperationalNotificationType::InventoryStockLow,
                            OperationalNotificationType::InventoryStockOut,
                        ],
                        resolutionAction: 'refill_completed',
                    );
                }
            }

            $audience = $this->adminOperatorAudience();
            $requester = $request->requestedBy;
            if ($requester && $requester->is_active && $requester->role === UserRole::Barista) {
                $audience[] = $requester;
            }

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: 'inventory.refill_updated:InventoryRefillRequest:'.$request->getKey().':'.$toStatus->value,
                type: OperationalNotificationType::InventoryRefillUpdated->value,
                category: 'inventory',
                title: 'Refill '.$toStatus->label(),
                message: 'Refill for '.($request->ingredient?->name ?? 'ingredient').' is now '.$toStatus->label().'.',
                audience: $audience,
                priority: OperationalNotificationPriority::Normal,
                actionRequired: false,
                actionUrl: $this->refillUrl($request),
                subject: $request,
                metadata: $this->safeRefillMeta($request),
            );
        });
    }

    /**
     * @return list<UserRole>
     */
    protected function adminOperatorAudience(): array
    {
        return [UserRole::Owner, UserRole::Manager, UserRole::Operator];
    }

    /**
     * @param  list<string>  $channels
     */
    protected function broadcastSignal(
        string $type,
        string $entity,
        int $entityId,
        ?string $name,
        ?string $state,
        array $channels,
        string $eventId,
    ): void {
        try {
            event(new InventoryOpsSignalBroadcasted($channels, [
                'event_id' => $eventId,
                'type' => $type,
                'entity' => $entity,
                'entity_id' => $entityId,
                'name' => $name,
                'state' => $state,
                'updated_at' => now()->toIso8601String(),
            ]));
        } catch (Throwable $exception) {
            Log::warning('Inventory ops signal broadcast failed.', [
                'event_id' => $eventId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{ingredient_id: int, name: string, state: string}
     */
    protected function safeIngredientMeta(Ingredient $ingredient, InventoryStockStatus $status): array
    {
        return [
            'ingredient_id' => (int) $ingredient->getKey(),
            'name' => (string) $ingredient->name,
            'state' => $status->value,
        ];
    }

    /**
     * @return array{refill_request_id: int, ingredient_id: int|null, name: string|null, state: string}
     */
    protected function safeRefillMeta(InventoryRefillRequest $request): array
    {
        return [
            'refill_request_id' => (int) $request->getKey(),
            'ingredient_id' => $request->ingredient_id ? (int) $request->ingredient_id : null,
            'name' => $request->ingredient?->name,
            'state' => $request->status instanceof InventoryRefillRequestStatus
                ? $request->status->value
                : (string) $request->status,
        ];
    }

    protected function inventoryUrl(Ingredient $ingredient): string
    {
        try {
            return route('administrator.inventory.index', ['ingredient_id' => $ingredient->getKey()]);
        } catch (Throwable) {
            return '/administrator/inventory';
        }
    }

    protected function refillUrl(InventoryRefillRequest $request): string
    {
        try {
            return route('administrator.inventory.refill-requests.show', $request);
        } catch (Throwable) {
            return '/administrator/inventory/refill-requests/'.$request->getKey();
        }
    }

    protected function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Operational inventory notification publishing failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
