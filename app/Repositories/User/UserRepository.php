<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\AbstractRepository;
use App\Transfers\User\UserFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository extends AbstractRepository implements UserRepositoryInterface
{
    public function __construct(
        protected User $model,
    ) {}

    public function paginateForAdministrator(UserFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters->getRole() === 'administrator', fn ($query) => $query->whereIn('role', ['owner', 'manager']))
            ->when($filters->getRole() === 'barista', fn ($query) => $query->where('role', 'barista'))
            ->when($filters->getRole() === 'customer', fn ($query) => $query->where('role', 'customer'))
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $attributes): User
    {
        /** @var User $user */
        $user = $this->persist($this->model->newInstance(), $attributes);

        return $user;
    }

    public function update(User $user, array $attributes): User
    {
        /** @var User $user */
        $user = $this->persist($user, $attributes);

        return $user;
    }

    public function delete(User $user): void
    {
        $this->remove($user);
    }

    public function countActiveAdministratorUsers(array $administratorRoles, ?int $excludeUserId = null): int
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->whereIn('role', $administratorRoles)
            ->when($excludeUserId, fn ($query) => $query->whereKeyNot($excludeUserId))
            ->count();
    }
}
