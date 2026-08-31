<?php

namespace App\Services\Notification;

use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Models\User;
use Illuminate\Support\Collection;

interface StaffNotificationDispatcherInterface
{
    public function notify(
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationAudience $audience,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void;

    public function notifyUser(
        User $user,
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void;

    /**
     * @return Collection<int, User>
     */
    public function recipientsFor(StaffNotificationAudience $audience): Collection;
}
