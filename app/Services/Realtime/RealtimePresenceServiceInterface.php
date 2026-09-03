<?php

namespace App\Services\Realtime;

use App\Enums\UserRole;
use App\Models\User;

interface RealtimePresenceServiceInterface
{
    /**
     * Record that a staff user is currently connected (advisory, TTL-based).
     */
    public function heartbeat(User $user): void;

    /**
     * Explicit leave / logout / disconnect.
     */
    public function leave(User $user): void;

    public function isRoleOnline(UserRole $role): bool;

    /**
     * Unique online user ids for a role (stale entries pruned).
     *
     * @return list<int>
     */
    public function onlineUserIdsForRole(UserRole $role): array;

    /**
     * Role-level unique online counts for operational dashboards.
     *
     * @return array<string, int>
     */
    public function summaryCounts(): array;

    /**
     * Map UserRole → presence bucket key used in cache/UI.
     */
    public function presenceKeyForUser(User $user): ?string;
}
