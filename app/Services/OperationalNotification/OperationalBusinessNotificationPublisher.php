<?php

namespace App\Services\OperationalNotification;

use App\Enums\OperationalNotificationPriority;
use App\Enums\OperationalNotificationType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Enums\UserRole;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\OrderPreparation;
use App\Models\User;
use App\Services\Realtime\RealtimePresenceServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * R1.3A / R1.4 / R1.5 bridge: domain events → OperationalNotificationService.
 * Never throws into the business event pipeline.
 */
class OperationalBusinessNotificationPublisher
{
    public function __construct(
        protected OperationalNotificationServiceInterface $notifications,
        protected RealtimePresenceServiceInterface $presence,
    ) {}

    public function handleOrderPlaced(Order $order): void
    {
        $this->safe(function () use ($order): void {
            if ($order->isDiningRound()) {
                $this->publishCustomerDiningRoundPlaced($order);

                return;
            }

            $this->publishCustomerOrderPlaced($order);

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
            $this->publishCustomerPaymentProofReceived($order, $isResubmission);

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

    public function handlePaymentProofRejected(Order $order, ?string $customerFacingReason = null): void
    {
        $this->safe(function () use ($order, $customerFacingReason): void {
            $this->notifications->resolveOpenForSubject(
                $order,
                [OperationalNotificationType::OrderPaymentProofReview],
                resolutionAction: 'proof_rejected',
            );

            $this->publishCustomerPaymentRejected($order, $customerFacingReason);
        });
    }

    public function handleOrderStatusChanged(Order $order, OrderStatus $fromStatus, OrderStatus $toStatus): void
    {
        $this->safe(function () use ($order, $fromStatus, $toStatus): void {
            $this->publishCustomerOrderStatusChanged($order, $toStatus);

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
                    [
                        OperationalNotificationType::DiningReadyToServe,
                        OperationalNotificationType::EscalationNoWaiterOnline,
                    ],
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
                    OperationalNotificationType::EscalationNoWaiterOnline,
                ],
                resolutionAction: $resolution,
            );

            $order->loadMissing('preparations');
            foreach ($order->preparations as $ticket) {
                $this->notifications->resolveOpenForSubject(
                    $ticket,
                    [
                        OperationalNotificationType::PreparationTicketPending,
                        OperationalNotificationType::EscalationNoBaristaOnline,
                        OperationalNotificationType::EscalationNoChefOnline,
                    ],
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
            $ticket->loadMissing(['order.items', 'order.preparations', 'order.customer']);

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

                $this->maybeEscalateMissingStationPresence($ticket);

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
                    [
                        OperationalNotificationType::PreparationTicketPending,
                        OperationalNotificationType::EscalationNoBaristaOnline,
                        OperationalNotificationType::EscalationNoChefOnline,
                    ],
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

            $this->maybeEscalateMissingWaiterPresence($order);
            $this->publishCustomerDiningReady($order);
        });
    }

    public function handleStaffRoleCameOnline(User $user): void
    {
        $this->safe(function () use ($user): void {
            $key = $this->presence->presenceKeyForUser($user);
            if ($key === null) {
                return;
            }

            $types = match ($key) {
                'barista' => [OperationalNotificationType::EscalationNoBaristaOnline],
                'chef' => [OperationalNotificationType::EscalationNoChefOnline],
                'waiter' => [OperationalNotificationType::EscalationNoWaiterOnline],
                default => [],
            };

            if ($types === []) {
                return;
            }

            $this->notifications->resolveOpenByTypes($types, $user, 'staff_online');
        });
    }

    protected function maybeEscalateMissingStationPresence(OrderPreparation $ticket): void
    {
        $role = match ($ticket->station) {
            PreparationStation::Bar => UserRole::Barista,
            PreparationStation::Kitchen => UserRole::Chef,
            default => null,
        };

        if ($role === null || $this->presence->isRoleOnline($role)) {
            return;
        }

        $type = $role === UserRole::Barista
            ? OperationalNotificationType::EscalationNoBaristaOnline
            : OperationalNotificationType::EscalationNoChefOnline;

        $label = $role === UserRole::Barista ? 'Barista' : 'Chef';
        $station = $ticket->station instanceof PreparationStation
            ? $ticket->station->label()
            : 'Station';

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key($type, $ticket, 'pending'),
            type: $type->value,
            category: 'escalation',
            title: 'No '.$label.' online',
            message: $station.' ticket for order '.($ticket->order?->order_number ?? '#'.$ticket->order_id).' is waiting and no '.$label.' is online.',
            audience: $this->operatorAdminAudience(),
            priority: OperationalNotificationPriority::High,
            actionRequired: true,
            actionCode: 'staff_coverage',
            actionUrl: $this->preparationUrl($ticket),
            subject: $ticket,
            metadata: ['advisory_presence' => true, 'missing_role' => $role->value],
        );
    }

    protected function maybeEscalateMissingWaiterPresence(Order $order): void
    {
        if ($this->presence->isRoleOnline(UserRole::Waiter)) {
            return;
        }

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key(OperationalNotificationType::EscalationNoWaiterOnline, $order, 'all_ready'),
            type: OperationalNotificationType::EscalationNoWaiterOnline->value,
            category: 'escalation',
            title: 'No Waiter online',
            message: 'Dining round '.$order->order_number.' is ready to serve and no Waiter is online.',
            audience: $this->operatorAdminAudience(),
            priority: OperationalNotificationPriority::High,
            actionRequired: true,
            actionCode: 'staff_coverage',
            actionUrl: $this->orderUrl($order),
            subject: $order,
            metadata: ['advisory_presence' => true, 'missing_role' => UserRole::Waiter->value],
        );
    }

    public function handleDiningRoundPlaced(Order $order, DiningSession $session): void
    {
        $this->safe(function () use ($order, $session): void {
            $this->publishCustomerDiningRoundPlaced($order, $session);
        });
    }

    public function handleDiningBillReady(DiningSession $session): void
    {
        $this->safe(function () use ($session): void {
            $audience = $this->customerAudience($session);
            if ($audience === []) {
                return;
            }

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerDiningBillRequested, $session, 'bill'),
                type: OperationalNotificationType::CustomerDiningBillRequested->value,
                category: 'dining',
                title: 'Bill ready',
                message: 'Your dining bill for '.$session->session_number.' is ready.',
                audience: $audience,
                priority: OperationalNotificationPriority::High,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $session,
                metadata: $this->customerSafeDiningMeta($session),
            );
        });
    }

    public function handleDiningPaymentConfirmed(DiningSession $session): void
    {
        $this->safe(function () use ($session): void {
            $audience = $this->customerAudience($session);
            if ($audience === []) {
                return;
            }

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerPaymentConfirmed, $session, 'confirmed'),
                type: OperationalNotificationType::CustomerPaymentConfirmed->value,
                category: 'payment',
                title: 'Payment confirmed',
                message: 'Payment for dining session '.$session->session_number.' is confirmed.',
                audience: $audience,
                priority: OperationalNotificationPriority::Normal,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $session,
                metadata: $this->customerSafeDiningMeta($session),
            );

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerDiningSessionClosed, $session, 'closed'),
                type: OperationalNotificationType::CustomerDiningSessionClosed->value,
                category: 'dining',
                title: 'Dining session closed',
                message: 'Your dining session '.$session->session_number.' is complete. Thank you!',
                audience: $audience,
                priority: OperationalNotificationPriority::Normal,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $session,
                metadata: $this->customerSafeDiningMeta($session),
            );
        });
    }

    public function handleDiningPaymentProofReceived(DiningSession $session, bool $isResubmission = false): void
    {
        $this->safe(function () use ($session, $isResubmission): void {
            $audience = $this->customerAudience($session);
            if ($audience === []) {
                return;
            }

            $stamp = $session->payment_proof_uploaded_at?->format('YmdHis') ?: now()->format('YmdHis');

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerPaymentProofReceived, $session, $stamp),
                type: OperationalNotificationType::CustomerPaymentProofReceived->value,
                category: 'payment',
                title: $isResubmission ? 'Payment proof resubmitted' : 'Payment proof received',
                message: 'We received your payment proof for '.$session->session_number.'. Awaiting verification.',
                audience: $audience,
                priority: OperationalNotificationPriority::Normal,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $session,
                metadata: $this->customerSafeDiningMeta($session),
            );
        });
    }

    public function handleDiningPaymentProofRejected(DiningSession $session, ?string $customerFacingReason = null): void
    {
        $this->safe(function () use ($session, $customerFacingReason): void {
            $audience = $this->customerAudience($session);
            if ($audience === []) {
                return;
            }

            $reason = filled($customerFacingReason)
                ? trim((string) $customerFacingReason)
                : 'Please upload a clearer payment screenshot.';

            $stamp = now()->format('YmdHis');

            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerPaymentRejected, $session, $stamp),
                type: OperationalNotificationType::CustomerPaymentRejected->value,
                category: 'payment',
                title: 'Payment proof needs attention',
                message: 'Payment proof for '.$session->session_number.' was not accepted. '.$reason,
                audience: $audience,
                priority: OperationalNotificationPriority::High,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $session,
                metadata: $this->customerSafeDiningMeta($session),
            );
        });
    }

    protected function publishCustomerDiningRoundPlaced(Order $order, ?DiningSession $session = null): void
    {
        $order->loadMissing('diningSession');
        $session ??= $order->diningSession;
        if (! $session instanceof DiningSession) {
            return;
        }

        $audience = $this->customerAudience($session);
        if ($audience === []) {
            return;
        }

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key(OperationalNotificationType::CustomerDiningRoundUpdated, $order, 'placed'),
            type: OperationalNotificationType::CustomerDiningRoundUpdated->value,
            category: 'dining',
            title: 'Round placed',
            message: 'Your dining round '.$order->order_number.' was sent to the kitchen.',
            audience: $audience,
            priority: OperationalNotificationPriority::Normal,
            actionRequired: false,
            actionUrl: $this->customerDiningUrl($session),
            subject: $order,
            metadata: $this->customerSafeDiningMeta($session, $order),
        );
    }

    protected function publishCustomerOrderPlaced(Order $order): void
    {
        if ($order->isDiningRound()) {
            return;
        }

        $audience = $this->customerAudience($order);
        if ($audience === []) {
            return;
        }

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key(OperationalNotificationType::CustomerOrderPlaced, $order, 'placed'),
            type: OperationalNotificationType::CustomerOrderPlaced->value,
            category: 'order',
            title: 'Order received',
            message: 'We received order '.$order->order_number.'.',
            audience: $audience,
            priority: OperationalNotificationPriority::Normal,
            actionRequired: false,
            actionUrl: $this->customerOrderUrl($order),
            subject: $order,
            metadata: $this->customerSafeOrderMeta($order),
        );
    }

    protected function publishCustomerPaymentProofReceived(Order $order, bool $isResubmission): void
    {
        $audience = $this->customerAudience($order);
        if ($audience === []) {
            return;
        }

        $stamp = $order->payment_proof_uploaded_at?->format('YmdHis') ?: now()->format('YmdHis');

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key(OperationalNotificationType::CustomerPaymentProofReceived, $order, $stamp),
            type: OperationalNotificationType::CustomerPaymentProofReceived->value,
            category: 'payment',
            title: $isResubmission ? 'Payment proof resubmitted' : 'Payment proof received',
            message: 'We received your payment proof for order '.$order->order_number.'. Awaiting verification.',
            audience: $audience,
            priority: OperationalNotificationPriority::Normal,
            actionRequired: false,
            actionUrl: $this->customerOrderUrl($order),
            subject: $order,
            metadata: $this->customerSafeOrderMeta($order),
        );
    }

    protected function publishCustomerPaymentRejected(Order $order, ?string $customerFacingReason): void
    {
        $audience = $this->customerAudience($order);
        if ($audience === []) {
            return;
        }

        $reason = filled($customerFacingReason)
            ? trim((string) $customerFacingReason)
            : 'Please upload a clearer payment screenshot.';

        $stamp = now()->format('YmdHis');

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key(OperationalNotificationType::CustomerPaymentRejected, $order, $stamp),
            type: OperationalNotificationType::CustomerPaymentRejected->value,
            category: 'payment',
            title: 'Payment proof needs attention',
            message: 'Payment proof for order '.$order->order_number.' was not accepted. '.$reason,
            audience: $audience,
            priority: OperationalNotificationPriority::High,
            actionRequired: false,
            actionUrl: $this->customerOrderUrl($order),
            subject: $order,
            metadata: $this->customerSafeOrderMeta($order),
        );
    }

    protected function publishCustomerOrderStatusChanged(Order $order, OrderStatus $toStatus): void
    {
        if ($order->isDiningRound()) {
            $this->publishCustomerDiningRoundStatus($order, $toStatus);

            return;
        }

        $audience = $this->customerAudience($order);
        if ($audience === []) {
            return;
        }

        $map = match ($toStatus) {
            OrderStatus::PaymentConfirmed => [
                'type' => OperationalNotificationType::CustomerPaymentConfirmed,
                'title' => 'Payment confirmed',
                'message' => 'Payment for order '.$order->order_number.' is confirmed.',
                'priority' => OperationalNotificationPriority::Normal,
            ],
            OrderStatus::Accepted => [
                'type' => OperationalNotificationType::CustomerOrderAccepted,
                'title' => 'Order accepted',
                'message' => 'Order '.$order->order_number.' was accepted and will be prepared soon.',
                'priority' => OperationalNotificationPriority::Normal,
            ],
            OrderStatus::Preparing => [
                'type' => OperationalNotificationType::CustomerOrderPreparing,
                'title' => 'Preparing your order',
                'message' => 'Order '.$order->order_number.' is being prepared.',
                'priority' => OperationalNotificationPriority::Normal,
            ],
            OrderStatus::ReadyForPickup => [
                'type' => OperationalNotificationType::CustomerOrderReady,
                'title' => $this->customerReadyTitle($order),
                'message' => $this->customerReadyMessage($order),
                'priority' => OperationalNotificationPriority::High,
            ],
            OrderStatus::Completed => [
                'type' => OperationalNotificationType::CustomerOrderCompleted,
                'title' => 'Order completed',
                'message' => 'Order '.$order->order_number.' is complete. Thank you!',
                'priority' => OperationalNotificationPriority::Normal,
            ],
            OrderStatus::Cancelled => [
                'type' => OperationalNotificationType::CustomerOrderCancelled,
                'title' => 'Order cancelled',
                'message' => 'Order '.$order->order_number.' was cancelled.',
                'priority' => OperationalNotificationPriority::High,
            ],
            OrderStatus::Rejected => [
                'type' => OperationalNotificationType::CustomerOrderRejected,
                'title' => 'Order rejected',
                'message' => 'Order '.$order->order_number.' was rejected.',
                'priority' => OperationalNotificationPriority::High,
            ],
            default => null,
        };

        if ($map === null) {
            return;
        }

        /** @var OperationalNotificationType $type */
        $type = $map['type'];

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key($type, $order, $toStatus->value),
            type: $type->value,
            category: str_starts_with($type->value, 'customer.payment') ? 'payment' : 'order',
            title: $map['title'],
            message: $map['message'],
            audience: $audience,
            priority: $map['priority'],
            actionRequired: false,
            actionUrl: $this->customerOrderUrl($order),
            subject: $order,
            metadata: $this->customerSafeOrderMeta($order),
        );
    }

    protected function publishCustomerDiningRoundStatus(Order $order, OrderStatus $toStatus): void
    {
        $order->loadMissing('diningSession');
        $session = $order->diningSession;
        if (! $session instanceof DiningSession) {
            return;
        }

        $audience = $this->customerAudience($session);
        if ($audience === []) {
            return;
        }

        if (in_array($toStatus, [OrderStatus::Accepted, OrderStatus::Preparing], true)) {
            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerDiningRoundUpdated, $order, $toStatus->value),
                type: OperationalNotificationType::CustomerDiningRoundUpdated->value,
                category: 'dining',
                title: $toStatus === OrderStatus::Preparing ? 'Round preparing' : 'Round accepted',
                message: 'Dining round '.$order->order_number.' is now '.$toStatus->label().'.',
                audience: $audience,
                priority: OperationalNotificationPriority::Normal,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $order,
                metadata: $this->customerSafeDiningMeta($session, $order),
            );

            return;
        }

        if ($toStatus === OrderStatus::Cancelled) {
            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerOrderCancelled, $order, $toStatus->value),
                type: OperationalNotificationType::CustomerOrderCancelled->value,
                category: 'dining',
                title: 'Round cancelled',
                message: 'Dining round '.$order->order_number.' was cancelled.',
                audience: $audience,
                priority: OperationalNotificationPriority::High,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $order,
                metadata: $this->customerSafeDiningMeta($session, $order),
            );

            return;
        }

        if ($toStatus === OrderStatus::Rejected) {
            $this->notifications->createUniqueAndBroadcast(
                idempotencyKey: $this->key(OperationalNotificationType::CustomerOrderRejected, $order, $toStatus->value),
                type: OperationalNotificationType::CustomerOrderRejected->value,
                category: 'dining',
                title: 'Round rejected',
                message: 'Dining round '.$order->order_number.' was rejected.',
                audience: $audience,
                priority: OperationalNotificationPriority::High,
                actionRequired: false,
                actionUrl: $this->customerDiningUrl($session),
                subject: $order,
                metadata: $this->customerSafeDiningMeta($session, $order),
            );
        }
    }

    protected function publishCustomerDiningReady(Order $order): void
    {
        $order->loadMissing('diningSession');
        $session = $order->diningSession;
        if (! $session instanceof DiningSession) {
            return;
        }

        $audience = $this->customerAudience($session);
        if ($audience === []) {
            return;
        }

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: $this->key(OperationalNotificationType::CustomerDiningReady, $order, 'all_ready'),
            type: OperationalNotificationType::CustomerDiningReady->value,
            category: 'dining',
            title: 'Your food is ready',
            message: 'Dining round '.$order->order_number.' is ready.',
            audience: $audience,
            priority: OperationalNotificationPriority::High,
            actionRequired: false,
            actionUrl: $this->customerDiningUrl($session),
            subject: $order,
            metadata: $this->customerSafeDiningMeta($session, $order),
        );
    }

    /**
     * @return list<User>
     */
    protected function customerAudience(Order|DiningSession $subject): array
    {
        $subject->loadMissing('customer');
        $customer = $subject->customer;

        if (! $customer instanceof User || ! $customer->is_active) {
            return [];
        }

        return [$customer];
    }

    /**
     * @return array{order_number?: string, public_status?: string|null, fulfilment_method?: string|null, session_number?: string}
     */
    protected function customerSafeOrderMeta(Order $order): array
    {
        return array_filter([
            'order_number' => (string) $order->order_number,
            'public_status' => $order->status instanceof OrderStatus ? $order->status->value : null,
            'fulfilment_method' => $order->fulfilment_method instanceof OrderFulfilmentMethod
                ? $order->fulfilment_method->value
                : null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array{session_number?: string, order_number?: string, public_status?: string|null}
     */
    protected function customerSafeDiningMeta(DiningSession $session, ?Order $order = null): array
    {
        return array_filter([
            'session_number' => (string) $session->session_number,
            'order_number' => $order?->order_number ? (string) $order->order_number : null,
            'public_status' => $order?->status instanceof OrderStatus ? $order->status->value : null,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    protected function customerReadyTitle(Order $order): string
    {
        return match ($order->fulfilment_method) {
            OrderFulfilmentMethod::Delivery => 'Ready for delivery',
            OrderFulfilmentMethod::DineIn => 'Ready to serve',
            default => 'Ready for pickup',
        };
    }

    protected function customerReadyMessage(Order $order): string
    {
        $label = $order->customerLabelForStatus(OrderStatus::ReadyForPickup);

        return 'Order '.$order->order_number.' is '.$label.'.';
    }

    protected function customerOrderUrl(Order $order): string
    {
        return '/orders/'.$order->getKey();
    }

    protected function customerDiningUrl(DiningSession $session): string
    {
        return '/dining/sessions/'.$session->getKey();
    }

    /**
     * @return list<UserRole>
     */
    protected function operatorAdminAudience(): array
    {
        return [UserRole::Owner, UserRole::Manager, UserRole::Operator];
    }

    protected function key(
        OperationalNotificationType $type,
        Order|OrderPreparation|DiningSession $subject,
        string $lifecycle,
    ): string {
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
