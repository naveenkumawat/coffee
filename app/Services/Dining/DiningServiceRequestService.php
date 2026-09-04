<?php

namespace App\Services\Dining;

use App\Enums\DiningServiceRequestStatus;
use App\Enums\DiningServiceRequestType;
use App\Enums\OperationalNotificationPriority;
use App\Enums\OperationalNotificationType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\EscalateDiningServiceRequestJob;
use App\Models\DiningServiceRequest;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\User;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiningServiceRequestService implements DiningServiceRequestServiceInterface
{
    public const ESCALATION_SECONDS = 60;

    public function __construct(
        protected OperationalNotificationServiceInterface $notifications,
        protected DiningRealtimePublisher $realtime,
    ) {}

    public function createOrderAssistance(DiningSession $session, User $customer): DiningServiceRequest
    {
        if (! $session->allowsNewRounds()) {
            throw ValidationException::withMessages([
                'session' => 'Waiter calls are only available for open dining sessions.',
            ]);
        }

        if ((int) $session->customer_id !== (int) $customer->getKey()) {
            throw ValidationException::withMessages([
                'session' => 'You can only call a waiter for your own dining session.',
            ]);
        }

        return DB::transaction(function () use ($session, $customer): DiningServiceRequest {
            /** @var DiningSession $locked */
            $locked = DiningSession::query()->lockForUpdate()->findOrFail($session->getKey());

            $existing = $this->openRequestQuery($locked, DiningServiceRequestType::OrderAssistance)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof DiningServiceRequest) {
                return $existing->fresh([
                    'diningSession.cafeTable',
                    'preferredWaiter',
                    'claimedBy',
                    'table',
                ]) ?? $existing;
            }

            $preferred = $this->resolvePreferredWaiter($locked);
            $broadcastImmediately = $preferred === null;

            $request = DiningServiceRequest::query()->create([
                'dining_session_id' => $locked->getKey(),
                'table_id' => $locked->cafe_table_id,
                'customer_id' => $customer->getKey(),
                'type' => DiningServiceRequestType::OrderAssistance,
                'status' => DiningServiceRequestStatus::Pending,
                'preferred_waiter_user_id' => $preferred?->getKey(),
                'expires_at' => $preferred
                    ? now()->addSeconds(self::ESCALATION_SECONDS)
                    : null,
                'escalated_at' => null,
            ]);

            if ($broadcastImmediately) {
                $this->notifyWaiters($request, preferredOnly: false, escalated: false);
            } else {
                $this->notifyWaiters($request, preferredOnly: true, escalated: false);
                EscalateDiningServiceRequestJob::dispatch((int) $request->getKey())
                    ->delay(now()->addSeconds(self::ESCALATION_SECONDS))
                    ->afterCommit();
            }

            $this->realtime->signalSession(
                $locked,
                'service.requested',
                'pending',
                null,
                'svc-'.$request->getKey(),
            );

            return $request->fresh([
                'diningSession.cafeTable',
                'preferredWaiter',
                'claimedBy',
                'table',
            ]) ?? $request;
        });
    }

    public function currentForSession(DiningSession $session, ?DiningServiceRequestType $type = null): ?DiningServiceRequest
    {
        return $this->openRequestQuery($session, $type ?? DiningServiceRequestType::OrderAssistance)
            ->with(['preferredWaiter', 'claimedBy', 'table'])
            ->latest('id')
            ->first();
    }

    public function cancel(DiningServiceRequest $request, User $customer): DiningServiceRequest
    {
        return DB::transaction(function () use ($request, $customer): DiningServiceRequest {
            /** @var DiningServiceRequest $locked */
            $locked = DiningServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ((int) $locked->customer_id !== (int) $customer->getKey()) {
                throw ValidationException::withMessages([
                    'request' => 'You can only cancel your own waiter request.',
                ]);
            }

            if ($locked->status !== DiningServiceRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'request' => $locked->status === DiningServiceRequestStatus::Claimed
                        ? 'A waiter is already on the way.'
                        : 'This waiter request is no longer open.',
                ]);
            }

            $locked->forceFill([
                'status' => DiningServiceRequestStatus::Cancelled,
                'cancelled_at' => now(),
                'completion_reason' => 'cancelled_by_customer',
            ])->save();

            $this->resolveServiceNotifications($locked, 'cancelled_by_customer');
            $locked->loadMissing('diningSession');
            if ($locked->diningSession) {
                $this->realtime->signalSession(
                    $locked->diningSession,
                    'service.cancelled',
                    'cancelled',
                    null,
                    'svc-cancel-'.$locked->getKey(),
                );
            }

            return $locked->fresh(['preferredWaiter', 'claimedBy', 'table']) ?? $locked;
        });
    }

    public function claim(DiningServiceRequest $request, User $waiter): DiningServiceRequest
    {
        return DB::transaction(function () use ($request, $waiter): DiningServiceRequest {
            /** @var DiningServiceRequest $locked */
            $locked = DiningServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status === DiningServiceRequestStatus::Claimed) {
                if ((int) $locked->claimed_by_user_id === (int) $waiter->getKey()) {
                    return $locked->fresh(['preferredWaiter', 'claimedBy', 'table', 'diningSession']) ?? $locked;
                }

                throw ValidationException::withMessages([
                    'request' => 'Another waiter is handling this request.',
                ]);
            }

            if ($locked->status !== DiningServiceRequestStatus::Pending) {
                throw ValidationException::withMessages([
                    'request' => 'This waiter request is no longer available to claim.',
                ]);
            }

            $locked->forceFill([
                'status' => DiningServiceRequestStatus::Claimed,
                'claimed_by_user_id' => $waiter->getKey(),
                'acknowledged_at' => now(),
            ])->save();

            $this->resolveServiceNotifications($locked, 'claimed');
            $locked->loadMissing('diningSession');
            if ($locked->diningSession) {
                $this->realtime->signalSession(
                    $locked->diningSession,
                    'service.claimed',
                    'claimed',
                    null,
                    'svc-claim-'.$locked->getKey(),
                );
            }

            return $locked->fresh(['preferredWaiter', 'claimedBy', 'table', 'diningSession']) ?? $locked;
        });
    }

    public function complete(
        DiningServiceRequest $request,
        ?User $actor = null,
        string $reason = 'waiter_marked_done',
    ): DiningServiceRequest {
        return DB::transaction(function () use ($request, $actor, $reason): DiningServiceRequest {
            /** @var DiningServiceRequest $locked */
            $locked = DiningServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if (! $locked->isOpen()) {
                return $locked->fresh(['preferredWaiter', 'claimedBy', 'table']) ?? $locked;
            }

            $locked->forceFill([
                'status' => DiningServiceRequestStatus::Completed,
                'completed_at' => now(),
                'completed_by_user_id' => $actor?->getKey(),
                'completion_reason' => $reason,
                'acknowledged_at' => $locked->acknowledged_at ?? now(),
                'claimed_by_user_id' => $locked->claimed_by_user_id ?? $actor?->getKey(),
            ])->save();

            $this->resolveServiceNotifications($locked, $reason);
            $locked->loadMissing('diningSession');
            if ($locked->diningSession) {
                $this->realtime->signalSession(
                    $locked->diningSession,
                    'service.completed',
                    'completed',
                    null,
                    'svc-done-'.$locked->getKey(),
                );
            }

            return $locked->fresh(['preferredWaiter', 'claimedBy', 'table']) ?? $locked;
        });
    }

    public function escalateIfDue(DiningServiceRequest $request): DiningServiceRequest
    {
        return DB::transaction(function () use ($request): DiningServiceRequest {
            /** @var DiningServiceRequest $locked */
            $locked = DiningServiceRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status !== DiningServiceRequestStatus::Pending) {
                return $locked;
            }

            if ($locked->escalated_at !== null) {
                return $locked;
            }

            // Initial all-waiter broadcasts have no preferred waiter — nothing to escalate.
            if ($locked->preferred_waiter_user_id === null) {
                return $locked;
            }

            $dueAt = $locked->expires_at ?? $locked->created_at?->copy()->addSeconds(self::ESCALATION_SECONDS);
            if ($dueAt !== null && $dueAt->isFuture()) {
                return $locked;
            }

            $locked->forceFill([
                'escalated_at' => now(),
            ])->save();

            $this->notifyWaiters($locked, preferredOnly: false, escalated: true);
            $locked->loadMissing('diningSession');
            if ($locked->diningSession) {
                $this->realtime->signalSession(
                    $locked->diningSession,
                    'service.escalated',
                    'pending',
                    null,
                    'svc-esc-'.$locked->getKey(),
                );
            }

            return $locked->fresh(['preferredWaiter', 'claimedBy', 'table']) ?? $locked;
        });
    }

    public function completeOpenOrderAssistanceForWaiterRound(DiningSession $session, User $actor): void
    {
        if (! $this->isEligibleWaiterActor($actor)) {
            return;
        }

        $open = $this->openRequestQuery($session, DiningServiceRequestType::OrderAssistance)->get();

        foreach ($open as $request) {
            $this->complete($request, $actor, 'waiter_round_submitted');
        }
    }

    public function resolveOpenOrderAssistanceForCustomerSelfOrder(DiningSession $session, User $actor): void
    {
        if (! $actor->hasRole(UserRole::Customer)) {
            return;
        }

        $open = $this->openRequestQuery($session, DiningServiceRequestType::OrderAssistance)->get();

        foreach ($open as $request) {
            $this->complete($request, $actor, 'customer_self_ordered');
        }
    }

    public function openRequestsForWaiter(?User $waiter = null): Collection
    {
        $query = DiningServiceRequest::query()
            ->with(['diningSession.cafeTable', 'table', 'preferredWaiter', 'claimedBy'])
            ->whereIn('status', [
                DiningServiceRequestStatus::Pending->value,
                DiningServiceRequestStatus::Claimed->value,
            ])
            ->orderByRaw("case when status = 'pending' and escalated_at is not null then 0 when status = 'pending' then 1 else 2 end")
            ->orderBy('created_at');

        return $query->get();
    }

    public function pendingCountForWaiter(?User $waiter = null): int
    {
        return DiningServiceRequest::query()
            ->where('status', DiningServiceRequestStatus::Pending->value)
            ->count();
    }

    public function resolvePreferredWaiter(DiningSession $session): ?User
    {
        /** @var Order|null $latestRound */
        $latestRound = $session->orders()
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
            ->orderByDesc('dining_round_number')
            ->orderByDesc('id')
            ->first();

        if (! $latestRound instanceof Order) {
            return null;
        }

        $placerId = $latestRound->placed_by_user_id
            ?? $latestRound->statusHistory()
                ->where('to_status', OrderStatus::Accepted->value)
                ->orderBy('id')
                ->value('changed_by');

        if ($placerId === null) {
            return null;
        }

        /** @var User|null $placer */
        $placer = User::query()->find($placerId);

        if (! $placer instanceof User || ! $this->isEligibleWaiterActor($placer)) {
            return null;
        }

        return $placer;
    }

    protected function openRequestQuery(DiningSession $session, DiningServiceRequestType $type)
    {
        return DiningServiceRequest::query()
            ->where('dining_session_id', $session->getKey())
            ->where('type', $type->value)
            ->whereIn('status', [
                DiningServiceRequestStatus::Pending->value,
                DiningServiceRequestStatus::Claimed->value,
            ]);
    }

    protected function isEligibleWaiterActor(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Waiter;
    }

    protected function notifyWaiters(DiningServiceRequest $request, bool $preferredOnly, bool $escalated): void
    {
        $request->loadMissing(['diningSession.cafeTable', 'table', 'preferredWaiter']);
        $tableLabel = $request->diningSession?->tableDisplayLabel()
            ?? $request->table?->displayLabel()
            ?? 'Table';

        $audience = [];
        if ($preferredOnly && $request->preferredWaiter instanceof User && $this->isEligibleWaiterActor($request->preferredWaiter)) {
            $audience = [$request->preferredWaiter];
            $title = $tableLabel.' is calling you';
            $message = $tableLabel.' needs order assistance.';
            $type = OperationalNotificationType::DiningServiceRequested;
        } else {
            $audience = [UserRole::Waiter];
            $title = $escalated
                ? $tableLabel.' still needs assistance'
                : $tableLabel.' needs assistance';
            $message = $tableLabel.' is calling for order assistance.';
            $type = $escalated
                ? OperationalNotificationType::DiningServiceEscalated
                : OperationalNotificationType::DiningServiceRequested;
        }

        $session = $request->diningSession;
        $actionUrl = $session
            ? '/waiter/sessions/'.$session->getKey()
            : '/waiter';

        $lifecycle = ($escalated ? 'escalated' : 'created').'-'.$request->getKey();

        $this->notifications->createUniqueAndBroadcast(
            idempotencyKey: implode(':', [$type->value, 'DiningServiceRequest', (string) $request->getKey(), $lifecycle]),
            type: $type->value,
            category: 'dining',
            title: $title,
            message: $message,
            audience: $audience,
            priority: OperationalNotificationPriority::High,
            actionRequired: true,
            actionCode: 'attend_table',
            actionUrl: $actionUrl,
            subject: $request,
            actor: $request->customer,
            metadata: [
                'dining_service_request_id' => (int) $request->getKey(),
                'dining_session_id' => (int) $request->dining_session_id,
                'table_id' => (int) $request->table_id,
                'request_type' => $request->type instanceof DiningServiceRequestType
                    ? $request->type->value
                    : (string) $request->type,
                'escalated' => $escalated,
                'preferred_only' => $preferredOnly,
            ],
        );
    }

    protected function resolveServiceNotifications(DiningServiceRequest $request, string $resolutionAction): void
    {
        $this->notifications->resolveOpenForSubject(
            $request,
            [
                OperationalNotificationType::DiningServiceRequested,
                OperationalNotificationType::DiningServiceEscalated,
            ],
            resolutionAction: $resolutionAction,
        );
    }
}
