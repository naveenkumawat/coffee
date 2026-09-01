<?php

namespace App\Listeners\Staff;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Order\OrderPreparationStatusChanged;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;

class NotifyStaffOrderPreparationStatusChanged
{
    public function __construct(
        protected StaffNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handle(OrderPreparationStatusChanged $event): void
    {
        $ticket = $event->ticket->loadMissing(['order.items', 'order.customer', 'order.diningSession']);
        $context = StaffNotificationContext::forPreparation($ticket);
        $stationKey = $ticket->station instanceof PreparationStation
            ? $ticket->station->value
            : 'unknown';

        if ($event->toStatus === OrderPreparationStatus::Pending) {
            $audience = match ($ticket->station) {
                PreparationStation::Bar => StaffNotificationAudience::Baristas,
                PreparationStation::Kitchen => StaffNotificationAudience::Chefs,
                default => null,
            };

            if ($audience === null) {
                return;
            }

            $this->dispatcher->notify(
                StaffNotificationType::OrderPreparationPending,
                'staff:order_preparation:'.$ticket->getKey().':pending:'.$stationKey,
                $audience,
                $context,
                sendEmail: false,
            );

            return;
        }

        if ($event->toStatus !== OrderPreparationStatus::Ready) {
            return;
        }

        $this->dispatcher->notify(
            StaffNotificationType::OrderPreparationReady,
            'staff:order_preparation:'.$ticket->getKey().':ready',
            StaffNotificationAudience::Operators,
            $context,
            sendEmail: false,
        );

        $order = $ticket->order;

        if ($order === null || ! $order->isDiningRound()) {
            return;
        }

        $order->loadMissing('preparations');

        $activeTickets = $order->preparations->filter(
            fn ($prep): bool => $prep->status !== OrderPreparationStatus::Cancelled,
        );

        $allReady = $activeTickets->isNotEmpty()
            && $activeTickets->every(
                fn ($prep): bool => $prep->status === OrderPreparationStatus::Ready,
            );

        if (! $allReady) {
            return;
        }

        $this->dispatcher->notify(
            StaffNotificationType::DiningReadyToServe,
            'staff:order_preparation:dining_ready:'.$order->getKey(),
            StaffNotificationAudience::Waiters,
            StaffNotificationContext::forOrder($order),
            sendEmail: false,
        );
    }
}
