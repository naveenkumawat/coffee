<?php

namespace App\Services\Realtime;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Advisory staff presence tracker (R1.5).
 * TTL heartbeats — never business authority. Counts unique user ids, not tabs.
 */
class RealtimePresenceService implements RealtimePresenceServiceInterface
{
    public const TTL_SECONDS = 45;

    public const HEARTBEAT_INTERVAL_SECONDS = 20;

    /**
     * @var list<string>
     */
    public const TRACKED_KEYS = [
        'administrator',
        'operator',
        'barista',
        'chef',
        'waiter',
    ];

    public function heartbeat(User $user): void
    {
        $key = $this->presenceKeyForUser($user);
        if ($key === null) {
            return;
        }

        $userId = (int) $user->getKey();
        $now = time();

        Cache::put($this->userCacheKey($userId), [
            'role' => $key,
            'seen_at' => $now,
        ], now()->addSeconds(self::TTL_SECONDS));

        $members = $this->readRoleMembers($key);
        $members[$userId] = $now;
        $this->writeRoleMembers($key, $members);
    }

    public function leave(User $user): void
    {
        $userId = (int) $user->getKey();
        $cached = Cache::get($this->userCacheKey($userId));
        Cache::forget($this->userCacheKey($userId));

        $key = is_array($cached) && isset($cached['role'])
            ? (string) $cached['role']
            : $this->presenceKeyForUser($user);

        if ($key === null) {
            return;
        }

        $members = $this->readRoleMembers($key);
        unset($members[$userId]);
        $this->writeRoleMembers($key, $members);
    }

    public function isRoleOnline(UserRole $role): bool
    {
        return $this->onlineUserIdsForRole($role) !== [];
    }

    public function onlineUserIdsForRole(UserRole $role): array
    {
        $key = $this->presenceKeyForRole($role);
        if ($key === null) {
            return [];
        }

        return array_values(array_map('intval', array_keys($this->pruneRoleMembers($key))));
    }

    public function summaryCounts(): array
    {
        $counts = [];
        foreach (self::TRACKED_KEYS as $key) {
            $counts[$key] = count($this->pruneRoleMembers($key));
        }

        return $counts;
    }

    public function presenceKeyForUser(User $user): ?string
    {
        $role = $user->role;

        if (! $role instanceof UserRole) {
            return null;
        }

        return $this->presenceKeyForRole($role);
    }

    protected function presenceKeyForRole(UserRole $role): ?string
    {
        return match ($role) {
            UserRole::Owner, UserRole::Manager => 'administrator',
            UserRole::Operator => 'operator',
            UserRole::Barista => 'barista',
            UserRole::Chef => 'chef',
            UserRole::Waiter => 'waiter',
            default => null,
        };
    }

    /**
     * @return array<int, int> userId => seen_at
     */
    protected function readRoleMembers(string $key): array
    {
        $raw = Cache::get($this->roleCacheKey($key), []);

        if (! is_array($raw)) {
            return [];
        }

        $members = [];
        foreach ($raw as $userId => $seenAt) {
            $id = (int) $userId;
            $at = (int) $seenAt;
            if ($id > 0 && $at > 0) {
                $members[$id] = $at;
            }
        }

        return $members;
    }

    /**
     * @param  array<int, int>  $members
     */
    protected function writeRoleMembers(string $key, array $members): void
    {
        Cache::put($this->roleCacheKey($key), $members, now()->addSeconds(self::TTL_SECONDS * 3));
    }

    /**
     * @return array<int, int>
     */
    protected function pruneRoleMembers(string $key): array
    {
        $cutoff = time() - self::TTL_SECONDS;
        $members = array_filter(
            $this->readRoleMembers($key),
            static fn (int $seenAt): bool => $seenAt >= $cutoff,
        );
        $this->writeRoleMembers($key, $members);

        return $members;
    }

    protected function userCacheKey(int $userId): string
    {
        return 'realtime:presence:user:'.$userId;
    }

    protected function roleCacheKey(string $key): string
    {
        return 'realtime:presence:role:'.$key;
    }
}
