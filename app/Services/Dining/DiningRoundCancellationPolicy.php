<?php

namespace App\Services\Dining;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderPreparation;
use App\Models\User;
use App\Services\OrderInventory\OrderInventoryConsumptionServiceInterface;
use Illuminate\Validation\ValidationException;

/**
 * L1.2 canonical Dining round cancellation / exception matrix.
 *
 * Preparation Ready ≠ Served. Served corrections require future void/comp (not Cancel).
 *
 * @phpstan-type CancellationDecision array{
 *     can_cancel: bool,
 *     cancel_requires_reason: bool,
 *     can_void: bool,
 *     cancellation_blocked_reason: ?string,
 *     mode: 'normal'|'privileged'|'blocked'|'idempotent'
 * }
 */
class DiningRoundCancellationPolicy
{
    public function __construct(
        protected OrderInventoryConsumptionServiceInterface $inventoryConsumption,
    ) {}

    /**
     * @return CancellationDecision
     */
    public function evaluate(DiningSession $session, Order $order, ?User $actor = null): array
    {
        if (! $order->isDiningRound()) {
            return $this->blocked('Only dining rounds use this cancellation policy.');
        }

        if ((int) $order->dining_session_id !== (int) $session->getKey()) {
            return $this->blocked('That round does not belong to this dining session.');
        }

        if ($order->status === OrderStatus::Cancelled) {
            return [
                'can_cancel' => false,
                'cancel_requires_reason' => false,
                'can_void' => false,
                'cancellation_blocked_reason' => null,
                'mode' => 'idempotent',
            ];
        }

        if ($order->status === OrderStatus::Rejected) {
            return $this->blocked('Rejected rounds cannot be cancelled.');
        }

        if ($order->status === OrderStatus::Completed) {
            return $this->blocked('Completed rounds cannot be cancelled.');
        }

        if ($order->served_at !== null) {
            return $this->blocked(
                'This round has already been served and cannot be cancelled normally. Void/comp adjustment is not available yet.',
            );
        }

        if (in_array($session->status, [DiningSessionStatus::Closed, DiningSessionStatus::Cancelled], true)) {
            return $this->blocked('This dining session is closed and cannot be changed.');
        }

        if ($session->payment_status === PaymentStatus::Confirmed) {
            return $this->blocked(
                'Payment is confirmed. Ordinary round cancellation is blocked; refunds remain a separate workflow.',
            );
        }

        if (in_array($session->status, [
            DiningSessionStatus::BillingRequested,
            DiningSessionStatus::AwaitingPayment,
            DiningSessionStatus::Paid,
        ], true) || $session->hasFinalizedBill()) {
            return $this->blocked(
                'The bill has been requested or finalized. Round cancellation is blocked while the bill is frozen.',
            );
        }

        if ($session->status !== DiningSessionStatus::Open) {
            return $this->blocked('This dining session is not open for round cancellation.');
        }

        $materialPrepStarted = $this->inventoryConsumption->hasMaterialPreparationStarted($order);
        $isReady = $order->status === OrderStatus::ReadyForPickup
            || $this->allActiveTicketsReady($order);

        if ($materialPrepStarted || $isReady) {
            $allowed = $actor === null
                || $actor->canManageOrders()
                || $actor->canOperateOrders();

            if (! $allowed) {
                return $this->blocked(
                    'After preparation has started or the round is Ready, only Operator/Admin may cancel with a reason.',
                );
            }

            return [
                'can_cancel' => true,
                'cancel_requires_reason' => true,
                'can_void' => false,
                'cancellation_blocked_reason' => null,
                'mode' => 'privileged',
            ];
        }

        $allowed = $actor === null
            || $actor->canOperateDining()
            || $actor->canManageOrders()
            || $actor->canOperateOrders();

        if (! $allowed) {
            return $this->blocked('You are not allowed to cancel this dining round.');
        }

        return [
            'can_cancel' => true,
            'cancel_requires_reason' => false,
            'can_void' => false,
            'cancellation_blocked_reason' => null,
            'mode' => 'normal',
        ];
    }

    /**
     * @return CancellationDecision
     */
    public function assertMayCancel(
        DiningSession $session,
        Order $order,
        User $actor,
        ?string $reason = null,
        ?string $notes = null,
    ): array {
        $decision = $this->evaluate($session, $order, $actor);

        if ($decision['mode'] === 'idempotent') {
            return $decision;
        }

        if (! $decision['can_cancel']) {
            throw ValidationException::withMessages([
                'order' => $decision['cancellation_blocked_reason']
                    ?? 'This dining round cannot be cancelled.',
            ]);
        }

        if ($decision['cancel_requires_reason'] && ! filled($reason) && ! filled($notes)) {
            throw ValidationException::withMessages([
                'reason' => 'A cancellation reason is required after preparation has started or the round is Ready.',
            ]);
        }

        return $decision;
    }

    /**
     * @return CancellationDecision
     */
    protected function blocked(string $message): array
    {
        return [
            'can_cancel' => false,
            'cancel_requires_reason' => false,
            'can_void' => false,
            'cancellation_blocked_reason' => $message,
            'mode' => 'blocked',
        ];
    }

    protected function allActiveTicketsReady(Order $order): bool
    {
        $order->loadMissing('preparations');

        $active = $order->preparations->filter(
            static fn (OrderPreparation $ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled,
        );

        return $active->isNotEmpty()
            && $active->every(
                static fn (OrderPreparation $ticket): bool => $ticket->status === OrderPreparationStatus::Ready,
            );
    }
}
