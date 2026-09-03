<?php

namespace App\Services\OperationalNotification;

use App\Enums\OperationalNotificationPriority;
use App\Enums\OperationalNotificationType;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderPreparation;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * R1.3A bridge: domain events → OperationalNotificationService.
 * Never throws into the business event pipeline.
 */
class OperationalBusinessNotificationPublisher
{
    public function __construct(
        protected OperationalNotificationServiceInterface $notifications,
    ) {}

    public function handleOrderPlaced(Order $order): void
    {
        $this->safe(function () use ($order): void {
            if ($order->isDiningRound()) {
                return;
            }

            // UPI/retail awaiting customer proof is not staff-actionable yet.
            if (! $order->isCashPayment()) {
                return;
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                return;
            }

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::OrderRequiresAttention, $order, 'placed'),
                type: OperationalNotificationType::OrderRequiresAttention->value,
                category: 'order',
                title: 'Cash order needs attention',
                message: 'Order '.$order->order_number.' is awaiting payment confirmation or acceptance.',
                audience: $this->operatorAdminAudience(),
                priority: OperationalNotificationPriority::High,
                actionRequired: true,
                actionCode: 'review_order',
                actionUrl: $this->orderUrl($order),
                subject: $order,
            );
        });
    }

    public function handlePaymentProofReceived(Order $order, bool $isResubmission = false): void
    {
        $this->safe(function () use ($order, $isResubmission): void {
            if ($isResubmission) {
                $this->notifications->resolveOpenForSubject(
                    $order,
                    [OperationalNotificationType::OrderPaymentProofReview],
                    resolutionAction: 'proof_resubmitted',
                );
            }

            $stamp = $order->payment_proof_uploaded_at?->format('YmdHis') ?: now()->format('YmdHis');

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::OrderPaymentProofReview, $order, $stamp),
                type: OperationalNotificationType::OrderPaymentProofReview->value,
                category: 'payment',
                title: $isResubmission ? 'Payment proof resubmitted' : 'Payment proof needs review',
                message: 'Order '.$order->order_number.' has a payment proof awaiting verification.',
                audience: $this->operatorAdminAudience(),
                priority: OperationalNotificationPriority::High,
                actionRequired: true,
                actionCode: 'review_payment_proof',
                actionUrl: $this->orderUrl($order),
                subject: $order,
            );
        });
    }

    public function handlePaymentProofRejected(Order $order): void
    {
        $this->safe(function () use ($order): void {
            $this->notifications->resolveOpenForSubject(
                $order,
                [OperationalNotificationType::OrderPaymentProofReview],
                resolutionAction: 'proof_rejected',
            );
        });
    }

    public function handleOrderStatusChanged(Order $order, OrderStatus $fromStatus, OrderStatus $toStatus): void
    {
        $this->safe(function () use ($order, $fromStatus, $toStatus): void {
            if ($toStatus === OrderStatus::PaymentConfirmed && ! $order->isDiningRound()) {
                $this->notifications->resolveOpenForSubject(
                    $order,
                    [
                        OperationalNotificationType::OrderRequiresAttention,
                        OperationalNotificationType::OrderPaymentProofReview,
                    ],
                    resolutionAction: 'payment_confirmed',
                );

                $this->notifications->createUniqueAndBroadcast(
                    idempotencyKey: $this->key(OperationalNotificationType::OrderRequiresAcceptance, $order, 'payment_confirmed'),
                    type: OperationalNotificationType::OrderRequiresAcceptance->value,
                    category: 'order',
                    title: 'Order requires acceptance',
                    message: 'Order '.$order->order_number.' payment is confirmed and needs acceptance.',
                    audience: $this->operatorAdminAudience(),
                    priority: OperationalNotificationPriority::High,
                    actionRequired: true,
                    actionCode: 'accept_order',
                    actionUrl: $this->orderUrl($order),
                    subject: $order,
                );

                return;
            }

            if ($toStatus === OrderStatus::Accepted && ! $order->isDiningRound()) {
                $this->notifications->resolveOpenForSubject(
                    $order,
                    [
                        OperationalNotificationType::OrderRequiresAttention,
                        OperationalNotificationType::OrderRequiresAcceptance,
                        OperationalNotificationType::OrderPaymentProofReview,
                    ],
                    resolutionAction: 'accepted',
                );

                return;
            }

            if ($toStatus === OrderStatus::Completed && $order->isDiningRound()) {
                $this->notifications->resolveOpenForSubject(
                    $order,
                    [OperationalNotificationType::DiningReadyToServe],
                    resolutionAction: 'order_completed',
                );

                return;
            }

            if (! in_array($toStatus, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                return;
            }

            $resolution = $toStatus === OrderStatus::Rejected ? 'rejected' : 'cancelled';

            $this->notifications->resolveOpenForSubject(
                $order,
                [
                    OperationalNotificationType::OrderRequiresAttention,
                    OperationalNotificationType::OrderRequiresAcceptance,
                    OperationalNotificationType::OrderPaymentProofReview,
                    OperationalNotificationType::DiningReadyToServe,
                ],
                resolutionAction: $resolution,
            );

            $order->loadMissing('preparations');
            foreach ($order->preparations as $ticket) {
                $this->notifications->resolveOpenForSubject(
                    $ticket,
                    [OperationalNotificationType::PreparationTicketPending],
                    resolutionAction: $resolution,
                );
            }

            $infoType = $toStatus === OrderStatus::Rejected
                ? OperationalNotificationType::OrderRejected
                : OperationalNotificationType::OrderCancelled;

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key($infoType, $order, $toStatus->value),
                type: $infoType->value,
                category: 'order',
                title: $toStatus === OrderStatus::Rejected ? 'Order rejected' : 'Order cancelled',
                message: 'Order '.$order->order_number.' was '.$toStatus->value.'.',
                audience: $this->operatorAdminAudience(),
                priority: OperationalNotificationPriority::Normal,
                actionRequired: false,
                actionCode: null,
                actionUrl: $this->orderUrl($order),
                subject: $order,
            );

            if ($order->isDiningRound()) {
                $this->notifications->createUniqueAndBroadcast(
                    idempotencyKey: $this->key(OperationalNotificationType::DiningRoundCancelled, $order, $toStatus->value),
                    type: OperationalNotificationType::DiningRoundCancelled->value,
                    category: 'dining',
                    title: 'Dining round '.$toStatus->value,
                    message: 'Dining round '.$order->order_number.' was '.$toStatus->value.'.',
                    audience: [UserRole::Waiter, UserRole::Operator],
                    priority: OperationalNotificationPriority::Normal,
                    actionRequired: false,
                    actionUrl: $this->orderUrl($order),
                    subject: $order,
                );
            }

            unset($fromStatus);
        });
    }

    public function handlePreparationStatusChanged(
        OrderPreparation $ticket,
        ?OrderPreparationStatus $fromStatus,
        OrderPreparationStatus $toStatus,
    ): void {
        $this->safe(function () use ($ticket, $toStatus): void {
            $ticket->loadMissing(['order.items', 'order.preparations']);

            if ($toStatus === OrderPreparationStatus::Pending) {
                $audience = match ($ticket->station) {
                    PreparationStation::Bar => [UserRole::Barista],
                    PreparationStation::Kitchen => [UserRole::Chef],
                    default => [],
                };

                if ($audience === []) {
                    return;
                }

                $station = $ticket->station instanceof PreparationStation
                    ? $ticket->station->label()
                    : 'Station';

                $this->notifications->createUniqueAndBroadcast(
                    idempotencyKey: $this->key(
                        OperationalNotificationType::PreparationTicketPending,
                        $ticket,
                        'pending',
                    ),
                    type: OperationalNotificationType::PreparationTicketPending->value,
                    category: 'preparation',
                    title: $station.' ticket waiting',
                    message: $station.' preparation for order '.($ticket->order?->order_number ?? '#'.$ticket->order_id).' needs to be claimed.',
                    audience: $audience,
                    priority: OperationalNotificationPriority::High,
                    actionRequired: true,
                    actionCode: 'claim_ticket',
                    actionUrl: $this->preparationUrl($ticket),
                    subject: $ticket,
                );

                return;
            }

            // Lifecycle: resolve actionable pending alert on first meaningful station action (Accepted),
            // Cancelled, or Ready (if somehow skipped Accepted). Avoids continuous noise during Preparing.
            if (in_array($toStatus, [
                OrderPreparationStatus::Accepted,
                OrderPreparationStatus::Preparing,
                OrderPreparationStatus::Ready,
                OrderPreparationStatus::Cancelled,
            ], true)) {
                $this->notifications->resolveOpenForSubject(
                    $ticket,
                    [OperationalNotificationType::PreparationTicketPending],
                    resolutionAction: $toStatus->value,
                );
            }

            if ($toStatus === OrderPreparationStatus::Cancelled) {
                $audience = match ($ticket->station) {
                    PreparationStation::Bar => [UserRole::Barista],
                    PreparationStation::Kitchen => [UserRole::Chef],
                    default => [],
                };

                if ($audience !== []) {
                    $this->notifications->createUniqueAndBroadcast(
                        idempotencyKey: $this->key(
                            OperationalNotificationType::PreparationTicketCancelled,
                            $ticket,
                            'cancelled',
                        ),
                        type: OperationalNotificationType::PreparationTicketCancelled->value,
                        category: 'preparation',
                        title: 'Preparation ticket cancelled',
                        message: 'A '.strtolower($ticket->station?->label() ?? 'station').' ticket for order '.($ticket->order?->order_number ?? '#'.$ticket->order_id).' was cancelled.',
                        audience: $audience,
                        priority: OperationalNotificationPriority::Normal,
                        actionRequired: false,
                        actionUrl: $this->preparationUrl($ticket),
                        subject: $ticket,
                    );
                }
            }

            if ($toStatus !== OrderPreparationStatus::Ready) {
                return;
            }

            $order = $ticket->order;
            if ($order === null || ! $order->isDiningRound()) {
                return;
            }

            $order->loadMissing('preparations');
            $activeTickets = $order->preparations->filter(
                fn (OrderPreparation $prep): bool => $prep->status !== OrderPreparationStatus::Cancelled,
            );

            $allReady = $activeTickets->isNotEmpty()
                && $activeTickets->every(
                    fn (OrderPreparation $prep): bool => $prep->status === OrderPreparationStatus::Ready,
                );

            if (! $allReady) {
                return;
            }

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::DiningReadyToServe, $order, 'all_ready'),
                type: OperationalNotificationType::DiningReadyToServe->value,
                category: 'dining',
                title: 'Ready to serve',
                message: 'Dining round '.$order->order_number.' is ready to serve.',
                audience: [UserRole::Waiter, UserRole::Operator],
                priority: OperationalNotificationPriority::High,
                actionRequired: true,
                actionCode: 'serve_round',
                actionUrl: $this->orderUrl($order),
                subject: $order,
            );
        });
    }

    /**
     * @return list<UserRole>
     */
    protected function operatorAdminAudience(): array
    {
        return [UserRole::Owner, UserRole::Manager, UserRole::Operator];
    }

    protected function key(OperationalNotificationType $type, Order|OrderPreparation $subject, string $lifecycle): string
    {
        return implode(':', [
            $type->value,
            class_basename($subject),
            (string) $subject->getKey(),
            $lifecycle,
        ]);
    }

    protected function orderUrl(Order $order): string
    {
        try {
            return route('operator.orders.show', $order);
        } catch (Throwable) {
            return '/operator/orders/'.$order->getKey();
        }
    }

    protected function preparationUrl(OrderPreparation $ticket): string
    {
        $route = match ($ticket->station) {
            PreparationStation::Bar => 'barista.preparations.index',
            PreparationStation::Kitchen => 'chef.preparations.index',
            default => 'operator.preparations.index',
        };

        try {
            return route($route);
        } catch (Throwable) {
            return '/preparations';
        }
    }

    protected function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Operational business notification publishing failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
