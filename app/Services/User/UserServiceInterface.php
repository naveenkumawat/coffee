<?php

namespace App\Services\User;

use App\Models\User;
use App\Transfers\User\UserTransferInterface;

interface UserServiceInterface
{
    public function store(UserTransferInterface $data, User $actor): User;

    public function update(User $user, UserTransferInterface $data, User $actor): User;

    public function archive(User $user, User $actor): void;

    public function blockOrdering(User $user, User $actor, ?string $reason): User;

    public function unblockOrdering(User $user, User $actor): User;
}
