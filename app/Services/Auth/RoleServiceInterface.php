<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    public function all(): Collection;

    public function labels(): array;

    public function allowedAdminRoles(): array;

    public function canAccessAdmin(User $user): bool;
}
