<?php

namespace App\Listeners\Staff;

use App\Enums\InventoryRefillRequestStatus;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Enums\UserRole;
use App\Events\Inventory\InventoryRefillRequestCreated;
use App\Events\Inventory\InventoryRefillRequestStatusChanged;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;

class NotifyStaffInventoryRefillRequest
{
    public function __construct(
        protected StaffNotificationDispatcherInterface $dispatcher,
    ) {}

    public function handleCreated(InventoryRefillRequestCreated $event): void
    {
        $request = $event->refillRequest->loadMissing(['ingredient', 'requestedBy']);
        $context = StaffNotificationContext::forRefillRequest($request);

        $this->dispatcher->notify(
            StaffNotificationType::RefillRequestCreated,
            'staff:refill:'.$request->getKey().':'.InventoryRefillRequestStatus::Pending->value,
            StaffNotificationAudience::Administrators,
            $context,
            sendEmail: true,
        );
    }

    public function handleStatusChanged(InventoryRefillRequestStatusChanged $event): void
    {
        if ($event->fromStatus === $event->toStatus) {
            return;
        }

        $request = $event->refillRequest->loadMissing(['ingredient', 'requestedBy']);
        $context = StaffNotificationContext::forRefillRequest($request);
        $uniqueKey = 'staff:refill:'.$request->getKey().':'.$event->toStatus->value;

        $type = match ($event->toStatus) {
            InventoryRefillRequestStatus::Approved => StaffNotificationType::RefillRequestApproved,
            InventoryRefillRequestStatus::Rejected => StaffNotificationType::RefillRequestRejected,
            InventoryRefillRequestStatus::Completed => StaffNotificationType::RefillRequestCompleted,
            default => null,
        };

        if ($type === null) {
            return;
        }

        if ($event->toStatus === InventoryRefillRequestStatus::Approved) {
            // Approved refill still needs administrator stock intake.
            $this->dispatcher->notify(
                $type,
                $uniqueKey,
                StaffNotificationAudience::Administrators,
                $context,
                sendEmail: false,
            );
        }

        $requester = $request->requestedBy;

        if ($requester && $requester->is_active && $requester->hasRole(UserRole::Barista)) {
            $this->dispatcher->notifyUser(
                $requester,
                $type,
                $uniqueKey,
                $context,
                sendEmail: false,
            );
        }
    }
}
