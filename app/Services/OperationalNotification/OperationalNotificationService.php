<?php

namespace App\Services\OperationalNotification;

use App\Enums\OperationalNotificationPriority;
use App\Enums\OperationalNotificationType;
use App\Enums\UserRole;
use App\Events\Realtime\OperationalNotificationBroadcasted;
use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use App\Repositories\OperationalNotification\OperationalNotificationRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalNotificationService implements OperationalNotificationServiceInterface
{
    public function __construct(
        protected OperationalNotificationRepositoryInterface $notifications,
    ) {}

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
        ?string $idempotencyKey = null,
    ): OperationalNotification {
        $recipientRows = $this->normalizeAudience($audience);
        $metadata = $metadata ?? [];

        if ($recipientRows === []) {
            $metadata['intended_roles'] = $this->intendedRoleValues($audience);
            $metadata['no_active_recipients'] = true;
        }

        $notification = DB::transaction(function () use (
            $type,
            $category,
            $title,
            $message,
            $priority,
            $actionRequired,
            $actionCode,
            $actionUrl,
            $subject,
            $actor,
            $metadata,
            $recipientRows,
            $idempotencyKey,
        ): OperationalNotification {
            $attributes = [
                'type' => $type,
                'category' => $category,
                'priority' => $priority,
                'title' => $title,
                'message' => $message,
                'action_required' => $actionRequired,
                'action_code' => $actionCode,
                'action_url' => $actionUrl,
                'metadata' => $metadata === [] ? null : $metadata,
                'idempotency_key' => $idempotencyKey,
            ];

            if ($subject !== null) {
                $attributes['subject_type'] = $subject->getMorphClass();
                $attributes['subject_id'] = $subject->getKey();
            }

            if ($actor !== null) {
                $attributes['actor_type'] = $actor->getMorphClass();
                $attributes['actor_id'] = $actor->getKey();
            }

            $notification = $this->notifications->createNotification($attributes);

            if ($recipientRows !== []) {
                $this->notifications->createRecipients($notification, $recipientRows);
            }

            return $notification->load('recipients');
        });

        if ($broadcast && $notification->recipients->isNotEmpty()) {
            DB::afterCommit(function () use ($notification): void {
                $fresh = $notification->fresh(['recipients']) ?? $notification;
                $this->dispatchBroadcast($fresh);
            });
        }

        return $notification->fresh(['recipients']) ?? $notification;
    }

    public function createUniqueAndBroadcast(
        string $idempotencyKey,
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
    ): OperationalNotification {
        $existing = $this->notifications->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $this->createAndBroadcast(
                type: $type,
                category: $category,
                title: $title,
                message: $message,
                audience: $audience,
                priority: $priority,
                actionRequired: $actionRequired,
                actionCode: $actionCode,
                actionUrl: $actionUrl,
                subject: $subject,
                actor: $actor,
                metadata: $metadata,
                broadcast: $broadcast,
                idempotencyKey: $idempotencyKey,
            );
        } catch (QueryException $exception) {
            $existing = $this->notifications->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    public function resolveOpenForSubject(
        Model $subject,
        array $types = [],
        ?User $resolvedBy = null,
        ?string $resolutionAction = null,
    ): Collection {
        $typeValues = array_map(
            fn (string|OperationalNotificationType $type): string => $type instanceof OperationalNotificationType
                ? $type->value
                : $type,
            $types,
        );

        $resolved = collect();

        foreach ($this->notifications->findOpenForSubject($subject, $typeValues) as $notification) {
            $resolved->push($this->resolve($notification, $resolvedBy, $resolutionAction));
        }

        return $resolved;
    }

    public function listForUser(User $user, int $limit = 30, bool $actionRequiredOnly = false): Collection
    {
        return $this->notifications->listForUser($user, $limit, $actionRequiredOnly);
    }

    public function countsForUser(User $user): array
    {
        return [
            'unread_count' => $this->notifications->unreadCountForUser($user),
            'action_required_count' => $this->notifications->actionRequiredCountForUser($user),
        ];
    }

    public function markDelivered(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $recipient->markFirstTimestamp('delivered_at');

        return $recipient->fresh(['notification']) ?? $recipient;
    }

    public function markSeen(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $recipient->markFirstTimestamp('first_seen_at');
        $recipient->markFirstTimestamp('delivered_at');

        return $recipient->fresh(['notification']) ?? $recipient;
    }

    public function markRead(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $recipient->markFirstTimestamp('read_at');
        $recipient->markFirstTimestamp('first_seen_at');
        $recipient->markFirstTimestamp('delivered_at');

        return $recipient->fresh(['notification']) ?? $recipient;
    }

    public function acknowledge(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $recipient->markFirstTimestamp('acknowledged_at');
        $recipient->markFirstTimestamp('read_at');
        $recipient->markFirstTimestamp('first_seen_at');
        $recipient->markFirstTimestamp('delivered_at');

        return $recipient->fresh(['notification']) ?? $recipient;
    }

    public function markActionStarted(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $recipient->markFirstTimestamp('action_started_at');

        return $recipient->fresh(['notification']) ?? $recipient;
    }

    public function markActionCompleted(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $recipient->markFirstTimestamp('action_completed_at');
        $recipient->markFirstTimestamp('action_started_at');

        return $recipient->fresh(['notification']) ?? $recipient;
    }

    public function resolve(
        OperationalNotification $notification,
        ?User $resolvedBy = null,
        ?string $resolutionAction = null,
    ): OperationalNotification {
        return $this->notifications->resolveNotification($notification, $resolvedBy, $resolutionAction);
    }

    public function recordReminder(OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        return $this->notifications->incrementReminder($recipient);
    }

    public function metricsForRecipient(OperationalNotificationRecipient $recipient): array
    {
        $notification = $recipient->relationLoaded('notification')
            ? $recipient->notification
            : $recipient->notification()->first();

        $createdAt = $notification?->created_at;

        return [
            'delivery_delay_seconds' => $this->delaySeconds($createdAt, $recipient->delivered_at),
            'first_seen_delay_seconds' => $this->delaySeconds($createdAt, $recipient->first_seen_at),
            'acknowledge_delay_seconds' => $this->delaySeconds($createdAt, $recipient->acknowledged_at),
            'action_start_delay_seconds' => $this->delaySeconds($createdAt, $recipient->action_started_at),
            'action_completion_delay_seconds' => $this->delaySeconds($createdAt, $recipient->action_completed_at),
            'resolution_delay_seconds' => $this->delaySeconds($createdAt, $notification?->resolved_at),
        ];
    }

    /**
     * @param  list<User>|list<UserRole>  $audience
     * @return list<array{user_id: int, role: string}>
     */
    protected function normalizeAudience(array $audience): array
    {
        $rows = [];

        foreach ($audience as $item) {
            if ($item instanceof User) {
                if (! $item->is_active) {
                    continue;
                }

                $rows[(int) $item->getKey()] = [
                    'user_id' => (int) $item->getKey(),
                    'role' => $item->role instanceof UserRole ? $item->role->value : (string) $item->role,
                ];

                continue;
            }

            if ($item instanceof UserRole) {
                foreach ($this->notifications->activeUsersForRoles($item) as $user) {
                    $rows[(int) $user->getKey()] = [
                        'user_id' => (int) $user->getKey(),
                        'role' => $item->value,
                    ];
                }
            }
        }

        return array_values($rows);
    }

    /**
     * @param  list<User>|list<UserRole>  $audience
     * @return list<string>
     */
    protected function intendedRoleValues(array $audience): array
    {
        $roles = [];

        foreach ($audience as $item) {
            if ($item instanceof UserRole) {
                $roles[] = $item->value;
            } elseif ($item instanceof User && $item->role instanceof UserRole) {
                $roles[] = $item->role->value;
            }
        }

        return array_values(array_unique($roles));
    }

    protected function dispatchBroadcast(OperationalNotification $notification): void
    {
        $recipients = $notification->recipients;
        $ids = [];

        try {
            foreach ($recipients as $recipient) {
                event(OperationalNotificationBroadcasted::fromRecipient($notification, $recipient));
                $ids[] = (int) $recipient->getKey();
            }

            $this->notifications->markBroadcastAt($ids);
        } catch (Throwable $exception) {
            Log::warning('Operational notification broadcast failed.', [
                'notification_id' => $notification->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function delaySeconds(mixed $from, mixed $to): ?int
    {
        if ($from === null || $to === null) {
            return null;
        }

        return (int) max(0, $from->diffInSeconds($to, false));
    }
}
