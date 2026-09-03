<?php

namespace App\Services\Dining;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Events\Realtime\DiningOpsSignalBroadcasted;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderPreparation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * R1.6 thin dining/table realtime signals (socket = signal, REST = authority).
 */
class DiningRealtimePublisher
{
    public function signalSession(
        DiningSession $session,
        string $type,
        ?string $state = null,
        ?int $orderId = null,
        ?string $eventIdSuffix = null,
    ): void {
        $this->safe(function () use ($session, $type, $state, $orderId, $eventIdSuffix): void {
            $sessionId = (int) $session->getKey();
            $tableId = $session->cafe_table_id ? (int) $session->cafe_table_id : null;
            $suffix = $eventIdSuffix ?: ($state ?: 'ping');
            $eventId = implode(':', ['dining', $type, $sessionId, $suffix, (string) now()->timestamp]);

            $channels = [
                'dining-session.'.$sessionId,
                'role.waiter',
                'role.operator',
            ];

            if ($tableId) {
                $channels[] = 'table.'.$tableId;
            }

            event(new DiningOpsSignalBroadcasted($channels, [
                'event_id' => $eventId,
                'type' => $type,
                'session_id' => $sessionId,
                'table_id' => $tableId,
                'order_id' => $orderId,
                'state' => $state ?? ($session->status instanceof DiningSessionStatus
                    ? $session->status->value
                    : (string) $session->status),
                'updated_at' => now()->toIso8601String(),
            ]));
        });
    }

    public function sessionOpened(DiningSession $session): void
    {
        $this->signalSession($session, 'session.opened', DiningSessionStatus::Open->value, null, 'opened');
    }

    public function sessionClosed(DiningSession $session): void
    {
        $this->signalSession($session, 'session.closed', DiningSessionStatus::Closed->value, null, 'closed');
        $this->signalSession($session, 'table.released', 'available', null, 'released');
    }

    public function sessionReopened(DiningSession $session): void
    {
        $this->signalSession($session, 'session.updated', DiningSessionStatus::Open->value, null, 'reopened');
    }

    public function roundPlaced(Order $order, DiningSession $session): void
    {
        $this->signalSession(
            $session,
            'round.placed',
            $order->status instanceof OrderStatus ? $order->status->value : null,
            (int) $order->getKey(),
            'round-'.$order->getKey(),
        );
    }

    public function billRequested(DiningSession $session): void
    {
        $this->signalSession(
            $session,
            'bill.requested',
            $session->status instanceof DiningSessionStatus ? $session->status->value : null,
            null,
            'bill',
        );
    }

    public function paymentChanged(DiningSession $session, string $hint): void
    {
        $this->signalSession(
            $session,
            'payment.changed',
            $hint,
            null,
            'pay-'.$hint,
        );
    }

    public function preparationChanged(OrderPreparation $ticket): void
    {
        $this->safe(function () use ($ticket): void {
            $ticket->loadMissing('order.diningSession');
            $order = $ticket->order;
            if ($order === null || ! $order->isDiningRound()) {
                return;
            }

            $session = $order->diningSession;
            if (! $session instanceof DiningSession) {
                return;
            }

            $status = $ticket->status instanceof OrderPreparationStatus
                ? $ticket->status->value
                : (string) $ticket->status;

            $this->signalSession(
                $session,
                'preparation.progress',
                $status,
                (int) $order->getKey(),
                'prep-'.$ticket->getKey().'-'.$status,
            );

            if ($status !== OrderPreparationStatus::Ready->value) {
                return;
            }

            $order->loadMissing('preparations');
            $active = $order->preparations->filter(
                fn (OrderPreparation $prep): bool => $prep->status !== OrderPreparationStatus::Cancelled,
            );
            $allReady = $active->isNotEmpty() && $active->every(
                fn (OrderPreparation $prep): bool => $prep->status === OrderPreparationStatus::Ready,
            );

            if ($allReady) {
                // Existing dining.ready_to_serve operational notification remains authoritative for reminders.
                $this->signalSession(
                    $session,
                    'round.all_stations_ready',
                    'ready',
                    (int) $order->getKey(),
                    'all-ready-'.$order->getKey(),
                );
            }
        });
    }

    public function roundStatusChanged(Order $order): void
    {
        $this->safe(function () use ($order): void {
            if (! $order->isDiningRound()) {
                return;
            }

            $order->loadMissing('diningSession');
            $session = $order->diningSession;
            if (! $session instanceof DiningSession) {
                return;
            }

            $status = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            $type = in_array($status, [OrderStatus::Cancelled->value, OrderStatus::Rejected->value], true)
                ? 'round.cancelled'
                : 'round.updated';

            $this->signalSession($session, $type, $status, (int) $order->getKey(), 'order-'.$status);
        });
    }

    protected function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Dining realtime signal failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
