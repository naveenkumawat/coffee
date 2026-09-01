<?php

namespace App\Services\User;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Auth\RoleServiceInterface;
use App\Transfers\User\UserTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService implements UserServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $users,
        protected RoleServiceInterface $roles,
    ) {}

    public function store(UserTransferInterface $data, User $actor): User
    {
        $this->ensureActorCanManageUsers($actor);

        return DB::transaction(fn (): User => $this->users->create($data->toArray()));
    }

    public function update(User $user, UserTransferInterface $data, User $actor): User
    {
        $this->ensureActorCanManageUsers($actor);
        $this->ensureSafeUserMutation($user, $data, $actor);

        return DB::transaction(fn (): User => $this->users->update($user, $data->toArray()));
    }

    public function archive(User $user, User $actor): void
    {
        $this->ensureActorCanManageUsers($actor);

        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot archive your own administrator account.',
            ]);
        }

        if ($user->is_active && $user->isAdministratorRole() && $this->users->countActiveAdministratorUsers($this->roles->administratorRoleValues(), $user->getKey()) === 0) {
            throw ValidationException::withMessages([
                'user' => 'At least one active administrator account must remain available.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['is_active' => false])->save();
            $this->users->delete($user);
        });
    }

    public function blockOrdering(User $user, User $actor, ?string $reason): User
    {
        $this->ensureActorCanManageUsers($actor);
        $this->ensureCustomerOrderingTarget($user);

        $user->forceFill([
            'ordering_blocked' => true,
            'ordering_blocked_at' => now(),
            'ordering_blocked_reason' => filled($reason) ? trim($reason) : null,
        ])->save();

        return $user->fresh();
    }

    public function unblockOrdering(User $user, User $actor): User
    {
        $this->ensureActorCanManageUsers($actor);
        $this->ensureCustomerOrderingTarget($user);

        $user->forceFill([
            'ordering_blocked' => false,
            'ordering_blocked_at' => null,
            'ordering_blocked_reason' => null,
        ])->save();

        return $user->fresh();
    }

    protected function ensureActorCanManageUsers(User $actor): void
    {
        if (! $actor->canManageUsers()) {
            throw ValidationException::withMessages([
                'user' => 'This account cannot manage users.',
            ]);
        }
    }

    protected function ensureCustomerOrderingTarget(User $user): void
    {
        if ($user->role !== UserRole::Customer) {
            throw ValidationException::withMessages([
                'ordering' => 'Ordering restrictions apply to customer accounts only.',
            ]);
        }
    }

    protected function ensureSafeUserMutation(User $user, UserTransferInterface $data, User $actor): void
    {
        $willRemainActive = $data->isActive();
        $willRemainAdministrator = $this->roles->isAdministratorValue($data->getRole());

        if ($actor->is($user) && ! $willRemainActive) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own administrator account.',
            ]);
        }

        if ($actor->is($user) && ! $willRemainAdministrator) {
            throw ValidationException::withMessages([
                'role' => 'You cannot remove your own administrator access.',
            ]);
        }

        if ($user->is_active && $user->isAdministratorRole() && (! $willRemainActive || ! $willRemainAdministrator)) {
            if ($this->users->countActiveAdministratorUsers($this->roles->administratorRoleValues(), $user->getKey()) === 0) {
                $field = ! $willRemainActive ? 'is_active' : 'role';

                throw ValidationException::withMessages([
                    $field => 'At least one active administrator account must remain available.',
                ]);
            }
        }
    }
}
