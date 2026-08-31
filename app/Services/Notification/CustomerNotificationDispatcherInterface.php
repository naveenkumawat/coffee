<?php

namespace App\Services\Notification;

use App\Enums\CustomerNotificationType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\Notification;

interface CustomerNotificationDispatcherInterface
{
    public function sendOnce(
        CustomerNotificationType $type,
        string $uniqueKey,
        string $recipientEmail,
        Notification $notification,
        ?User $customer = null,
        ?Order $order = null,
        ?string $customerFacingReason = null,
    ): bool;
}
