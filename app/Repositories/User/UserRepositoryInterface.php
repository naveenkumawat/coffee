<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Transfers\User\UserFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginateForAdministrator(UserFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator;

    public function create(array $attributes): User;

    public function update(User $user, array $attributes): User;

    public function delete(User $user): void;

    public function countActiveAdministratorUsers(array $administratorRoles, ?int $excludeUserId = null): int;
}
