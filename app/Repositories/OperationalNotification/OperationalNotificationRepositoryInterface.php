<?php

namespace App\Repositories\OperationalNotification;

use App\Enums\UserRole;
use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface OperationalNotificationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createNotification(array $attributes): OperationalNotification;

    /**
     * @param  list<array{user_id: int, role: string}>  $recipients
     * @return Collection<int, OperationalNotificationRecipient>
     */
    public function createRecipients(OperationalNotification $notification, array $recipients): Collection;

    public function findRecipientForUser(int $recipientId, User $user): ?OperationalNotificationRecipient;

    public function findNotificationById(int $notificationId): ?OperationalNotification;

    public function findByIdempotencyKey(string $idempotencyKey): ?OperationalNotification;

    /**
     * @param  list<string>  $types
     * @return Collection<int, OperationalNotification>
     */
    public function findOpenForSubject(Model $subject, array $types = []): Collection;

    /**
     * @param  list<string>  $types
     * @return Collection<int, OperationalNotification>
     */
    public function findOpenByTypes(array $types): Collection;

    /**
     * @return Collection<int, OperationalNotificationRecipient>
     */
    public function listForUser(User $user, int $limit = 30, bool $actionRequiredOnly = false): Collection;

    public function unreadCountForUser(User $user): int;

    public function actionRequiredCountForUser(User $user): int;

    /**
     * @return Collection<int, User>
     */
    public function activeUsersForRoles(UserRole ...$roles): Collection;

    /**
     * @param  list<int>  $recipientIds
     */
    public function markBroadcastAt(array $recipientIds, ?Carbon $at = null): void;

    public function resolveNotification(
        OperationalNotification $notification,
        ?User $resolvedBy = null,
        ?string $resolutionAction = null,
        ?Carbon $at = null,
    ): OperationalNotification;

    public function incrementReminder(OperationalNotificationRecipient $recipient, ?Carbon $at = null): OperationalNotificationRecipient;
}
