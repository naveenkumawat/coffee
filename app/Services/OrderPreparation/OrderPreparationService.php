<?php

namespace App\Services\OrderPreparation;

use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Events\Order\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPreparation;
use App\Models\User;
use App\Repositories\Order\OrderRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPreparationService implements OrderPreparationServiceInterface
{
    public function __construct(
        protected OrderRepositoryInterface $orders,
    ) {}

    public function createTicketsForOrder(Order $order): void
    {
        $order->loadMissing('items');

        if ($order->preparations()->exists()) {
            return;
        }

        $missingStation = $order->items->contains(
            fn (OrderItem $item): bool => $item->preparation_station === null,
        );

        if ($missingStation && $order->status === OrderStatus::Accepted) {
            throw ValidationException::withMessages([
                'items' => 'Every order item must have a preparation station before preparation tickets can be created.',
            ]);
        }

        $stations = $order->items
            ->filter(fn (OrderItem $item): bool => $item->preparation_station instanceof PreparationStation)
            ->map(fn (OrderItem $item): PreparationStation => $item->preparation_station)
            ->unique(fn (PreparationStation $station): string => $station->value)
            ->values();

        foreach ($stations as $station) {
            $ticket = OrderPreparation::query()->create([
                'order_id' => $order->getKey(),
                'station' => $station->value,
                'status' => OrderPreparationStatus::Pending->value,
            ]);

            OrderPreparationStatusChanged::dispatch(
                $ticket->fresh(['order.items', 'acceptedBy', 'readyBy']),
                null,
                OrderPreparationStatus::Pending,
            );
        }
    }

    public function cancelTicketsForOrder(Order $order, ?User $actor = null): void
    {
        $order->loadMissing('preparations');

        foreach ($order->preparations as $ticket) {
            if (! $ticket->isActive() && $ticket->status !== OrderPreparationStatus::Ready) {
                continue;
            }

            if ($ticket->status === OrderPreparationStatus::Cancelled) {
                continue;
            }

            $fromStatus = $ticket->status;
            $ticket->forceFill([
                'status' => OrderPreparationStatus::Cancelled->value,
                'cancelled_at' => $ticket->cancelled_at ?: now(),
            ])->save();

            OrderPreparationStatusChanged::dispatch(
                $ticket->fresh(['order.items', 'acceptedBy', 'readyBy']),
                $fromStatus instanceof OrderPreparationStatus ? $fromStatus : null,
                OrderPreparationStatus::Cancelled,
            );
        }
    }

    public function transition(
        OrderPreparation $ticket,
        User $actor,
        OrderPreparationStatus $next,
        ?string $notes = null,
    ): OrderPreparation {
        return DB::transaction(function () use ($ticket, $actor, $next): OrderPreparation {
            $ticket->refresh();

            if (! $ticket->station instanceof PreparationStation) {
                throw ValidationException::withMessages([
                    'station' => 'The preparation ticket station is invalid.',
                ]);
            }

            if (! $actor->canPrepareStation($ticket->station)) {
                throw ValidationException::withMessages([
                    'status' => 'You are not allowed to update this preparation station.',
                ]);
            }

            if (! $ticket->canTransitionTo($next)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected preparation status transition is not allowed.',
                ]);
            }

            $fromStatus = $ticket->status;
            $attributes = ['status' => $next->value];

            match ($next) {
                OrderPreparationStatus::Accepted => $attributes = [
                    ...$attributes,
                    'accepted_at' => $ticket->accepted_at ?: now(),
                    'accepted_by_user_id' => $ticket->accepted_by_user_id ?: $actor->getKey(),
                ],
                OrderPreparationStatus::Preparing => $attributes = [
                    ...$attributes,
                    'preparing_at' => $ticket->preparing_at ?: now(),
                ],
                OrderPreparationStatus::Ready => $attributes = [
                    ...$attributes,
                    'ready_at' => $ticket->ready_at ?: now(),
                    'ready_by_user_id' => $ticket->ready_by_user_id ?: $actor->getKey(),
                    'completed_at' => $ticket->completed_at ?: now(),
                ],
                OrderPreparationStatus::Cancelled => $attributes = [
                    ...$attributes,
                    'cancelled_at' => $ticket->cancelled_at ?: now(),
                ],
                default => null,
            };

            $ticket->forceFill($attributes)->save();

            $ticket = $ticket->fresh(['order.items', 'acceptedBy', 'readyBy']);

            OrderPreparationStatusChanged::dispatch(
                $ticket,
                $fromStatus instanceof OrderPreparationStatus ? $fromStatus : null,
                $next,
            );

            if (in_array($next, [
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
                OrderPreparationStatus::Ready,
            ], true)) {
                $this->syncOrderStatusFromTickets($ticket->order->fresh(['preparations', 'items']), $actor);
            }

            return $ticket->fresh(['order.items', 'acceptedBy', 'readyBy']);
        });
    }

    public function syncOrderStatusFromTickets(Order $order, User $systemActor): void
    {
        $order->loadMissing('preparations');

        if (! $order->status instanceof OrderStatus || $order->status->isTerminal()) {
            return;
        }

        $activeTickets = $order->preparations->filter(
            fn (OrderPreparation $ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled,
        );

        if ($activeTickets->isEmpty()) {
            return;
        }

        $hasStartedWork = $activeTickets->contains(
            fn (OrderPreparation $ticket): bool => in_array(
                $ticket->status,
                [OrderPreparationStatus::Accepted, OrderPreparationStatus::Preparing],
                true,
            ),
        );

        $allReady = $activeTickets->every(
            fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Ready,
        );

        if ($allReady && $order->status !== OrderStatus::ReadyForPickup) {
            $this->applyOrderStatusSync(
                $order,
                $systemActor,
                OrderStatus::ReadyForPickup,
                'All preparation stations are ready.',
            );

            return;
        }

        if (
            $hasStartedWork
            && $order->status === OrderStatus::Accepted
        ) {
            $this->applyOrderStatusSync(
                $order,
                $systemActor,
                OrderStatus::Preparing,
                'Preparation started at a station.',
            );
        }
    }

    public function queueForStation(PreparationStation $station, ?string $statusFilter = null): Collection
    {
        return OrderPreparation::query()
            ->where('station', $station->value)
            ->when(
                filled($statusFilter),
                fn ($query) => $query->where('status', $statusFilter),
            )
            ->whereHas('order', function ($query): void {
                $query->whereNotIn('status', [
                    OrderStatus::Pending->value,
                    OrderStatus::PendingPayment->value,
                    OrderStatus::Cancelled->value,
                    OrderStatus::Rejected->value,
                ]);
            })
            ->with([
                'order.customer',
                'order.items',
                'order.diningSession.cafeTable',
                'acceptedBy',
                'readyBy',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    protected function applyOrderStatusSync(
        Order $order,
        User $actor,
        OrderStatus $nextStatus,
        string $notes,
    ): void {
        $currentStatus = $order->status;

        if (! $currentStatus instanceof OrderStatus || $currentStatus === $nextStatus) {
            return;
        }

        $attributes = ['status' => $nextStatus->value];

        match ($nextStatus) {
            OrderStatus::Preparing => $attributes['preparing_at'] = $order->preparing_at ?: now(),
            OrderStatus::ReadyForPickup => $attributes['ready_for_pickup_at'] = $order->ready_for_pickup_at ?: now(),
            default => null,
        };

        $order = $this->orders->update($order, $attributes);
        $this->orders->createStatusHistory($order, [
            'from_status' => $currentStatus->value,
            'to_status' => $nextStatus->value,
            'changed_by' => $actor->getKey(),
            'notes' => $notes,
        ]);

        $order = $order->fresh([
            'customer',
            'assignedBarista',
            'items.recipe.lines.ingredient.brand',
            'statusHistory.changedBy',
            'preparations',
        ]);

        OrderStatusChanged::dispatch($order, $currentStatus, $nextStatus, $notes);
    }
}
