<?php

namespace App\Services\OperationalNotification;

use App\Enums\OperationalNotificationPriority;
use App\Enums\UserRole;
use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface OperationalNotificationServiceInterface
{
    /**
     * @param  list<User>|list<UserRole>  $audience  Users and/or roles (roles expand to active users)
     * @param  array<string, mixed>|null  $metadata
     */
    public function createAndBroadcast(
        string $type,
        string $category,
        string $title,
        string $message,
        array $audience,
        OperationalNotificationPriority $priority = OperationalNotificationPriority::Normal,
        bool $actionRequired = false,
        ?string $actionCode = null,
        ?string $actionUrl = null,
        ?Model $subject = null,
        ?Model $actor = null,
        ?array $metadata = null,
        bool $broadcast = true,
    ): OperationalNotification;

    /**
     * @return Collection<int, OperationalNotificationRecipient>
     */
    public function listForUser(User $user, int $limit = 30, bool $actionRequiredOnly = false): Collection;

    /**
     * @return array{unread_count: int, action_required_count: int}
     */
    public function countsForUser(User $user): array;

    public function markDelivered(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    public function markSeen(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    public function markRead(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    public function acknowledge(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    public function markActionStarted(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    public function markActionCompleted(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    public function resolve(
        OperationalNotification $notification,
        ?User $resolvedBy = null,
        ?string $resolutionAction = null,
    ): OperationalNotification;

    public function recordReminder(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient;

    /**
     * @return array{
     *     delivery_delay_seconds: int|null,
     *     first_seen_delay_seconds: int|null,
     *     acknowledge_delay_seconds: int|null,
     *     action_start_delay_seconds: int|null,
     *     action_completion_delay_seconds: int|null,
     *     resolution_delay_seconds: int|null
     * }
     */
    public function metricsForRecipient(OperationalNotificationRecipient $recipient): array;
}
