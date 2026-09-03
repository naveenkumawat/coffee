<?php

namespace App\Repositories\OperationalNotification;

use App\Enums\UserRole;
use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use App\Repositories\AbstractRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperationalNotificationRepository extends AbstractRepository implements OperationalNotificationRepositoryInterface
{
    public function __construct(
        protected OperationalNotification $notificationModel,
        protected OperationalNotificationRecipient $recipientModel,
        protected User $userModel,
    ) {}

    public function createNotification(array $attributes): OperationalNotification
    {
        /** @var OperationalNotification $notification */
        $notification = $this->persist($this->notificationModel->newInstance(), $attributes);

        return $notification;
    }

    public function createRecipients(OperationalNotification $notification, array $recipients): Collection
    {
        $created = collect();

        foreach ($recipients as $recipient) {
            /** @var OperationalNotificationRecipient $row */
            $row = $this->persist($this->recipientModel->newInstance(), [
                'operational_notification_id' => $notification->getKey(),
                'user_id' => $recipient['user_id'],
                'role' => $recipient['role'],
            ]);
            $created->push($row);
        }

        return $created;
    }

    public function findRecipientForUser(int $recipientId, User $user): ?OperationalNotificationRecipient
    {
        return $this->recipientModel->newQuery()
            ->with('notification')
            ->whereKey($recipientId)
            ->where('user_id', $user->getKey())
            ->first();
    }

    public function findNotificationById(int $notificationId): ?OperationalNotification
    {
        return $this->notificationModel->newQuery()
            ->with('recipients')
            ->whereKey($notificationId)
            ->first();
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?OperationalNotification
    {
        return $this->notificationModel->newQuery()
            ->with('recipients')
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    public function findOpenForSubject(Model $subject, array $types = []): Collection
    {
        return $this->notificationModel->newQuery()
            ->with('recipients')
            ->whereNull('resolved_at')
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->when($types !== [], fn ($query) => $query->whereIn('type', $types))
            ->orderBy('id')
            ->get();
    }

    public function listForUser(User $user, int $limit = 30, bool $actionRequiredOnly = false): Collection
    {
        return $this->recipientModel->newQuery()
            ->with('notification')
            ->where('user_id', $user->getKey())
            ->when($actionRequiredOnly, function ($query): void {
                $query->whereHas('notification', function ($notificationQuery): void {
                    $notificationQuery
                        ->where('action_required', true)
                        ->whereNull('resolved_at');
                });
            })
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCountForUser(User $user): int
    {
        return (int) $this->recipientModel->newQuery()
            ->where('user_id', $user->getKey())
            ->whereNull('read_at')
            ->count();
    }

    public function actionRequiredCountForUser(User $user): int
    {
        return (int) $this->recipientModel->newQuery()
            ->where('user_id', $user->getKey())
            ->whereNull('acknowledged_at')
            ->whereHas('notification', function ($query): void {
                $query->where('action_required', true)->whereNull('resolved_at');
            })
            ->count();
    }

    public function activeUsersForRoles(UserRole ...$roles): Collection
    {
        if ($roles === []) {
            return collect();
        }

        return $this->userModel->newQuery()
            ->where('is_active', true)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, $roles))
            ->orderBy('id')
            ->get();
    }

    public function markBroadcastAt(array $recipientIds, ?Carbon $at = null): void
    {
        if ($recipientIds === []) {
            return;
        }

        $this->recipientModel->newQuery()
            ->whereIn('id', $recipientIds)
            ->whereNull('broadcast_at')
            ->update(['broadcast_at' => $at ?? now()]);
    }

    public function resolveNotification(
        OperationalNotification $notification,
        ?User $resolvedBy = null,
        ?string $resolutionAction = null,
        ?Carbon $at = null,
    ): OperationalNotification {
        if ($notification->resolved_at !== null) {
            return $notification->fresh() ?? $notification;
        }

        $attributes = [
            'resolved_at' => $at ?? now(),
            'resolution_action' => $resolutionAction,
        ];

        if ($resolvedBy !== null) {
            $attributes['resolved_by_type'] = $resolvedBy->getMorphClass();
            $attributes['resolved_by_id'] = $resolvedBy->getKey();
        }

        /** @var OperationalNotification $updated */
        $updated = $this->persist($notification, $attributes);

        return $updated;
    }

    public function incrementReminder(OperationalNotificationRecipient $recipient, ?Carbon $at = null): OperationalNotificationRecipient
    {
        $recipient->forceFill([
            'reminder_count' => ((int) $recipient->reminder_count) + 1,
            'last_reminded_at' => $at ?? now(),
        ])->save();

        return $recipient->fresh() ?? $recipient;
    }
}
