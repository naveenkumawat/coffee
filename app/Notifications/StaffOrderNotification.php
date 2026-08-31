<?php

namespace App\Notifications;

use App\Enums\StaffNotificationType;
use App\Models\Order;
use App\Services\Notification\StaffNotificationContext;

/**
 * Order-focused constructor wrapper around StaffOperationalNotification.
 */
class StaffOrderNotification extends StaffOperationalNotification
{
    public Order $order;

    /**
     * @param  list<string>  $channels
     */
    public function __construct(
        Order $order,
        StaffNotificationType $type,
        array $channels = ['database'],
    ) {
        $this->order = $order;

        parent::__construct($type, StaffNotificationContext::forOrder($order), $channels);
    }
}
