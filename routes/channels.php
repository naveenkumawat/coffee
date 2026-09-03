<?php

use App\Enums\UserRole;
use App\Models\CafeTable;
use App\Models\DiningSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| R1.1 Private channel authorization
|--------------------------------------------------------------------------
|
| Guards: web (customer/waiter SPA + customer Blade) and admin (staff panels).
| Customers may only join private user.{id}. Staff role channels map to panels:
| administrator = owner|manager. Do not subscribe customers to staff channels.
|
*/

Broadcast::channel('user.{id}', function (?User $user, int|string $id): bool {
    return $user !== null && (int) $user->id === (int) $id;
}, ['guards' => ['web', 'admin']]);

Broadcast::channel('role.administrator', function (?User $user): bool {
    return $user?->role?->canAccessAdministratorPanel() === true;
}, ['guards' => ['web', 'admin']]);

Broadcast::channel('role.operator', function (?User $user): bool {
    return $user?->role === UserRole::Operator;
}, ['guards' => ['web', 'admin']]);

Broadcast::channel('role.barista', function (?User $user): bool {
    return $user?->role === UserRole::Barista;
}, ['guards' => ['web', 'admin']]);

Broadcast::channel('role.chef', function (?User $user): bool {
    return $user?->role === UserRole::Chef;
}, ['guards' => ['web', 'admin']]);

Broadcast::channel('role.waiter', function (?User $user): bool {
    return $user?->role === UserRole::Waiter;
}, ['guards' => ['web', 'admin']]);

/**
 * R1.5 advisory staff presence (Echo PresenceChannel → presence-ops).
 * Customers denied. Payload is id + role label only — no profile secrets.
 */
Broadcast::channel('ops', function (?User $user): array|bool {
    if ($user === null || $user->role === UserRole::Customer) {
        return false;
    }

    $role = $user->role;
    if (! $role instanceof UserRole) {
        return false;
    }

    $presenceRole = match ($role) {
        UserRole::Owner, UserRole::Manager => 'administrator',
        UserRole::Operator => 'operator',
        UserRole::Barista => 'barista',
        UserRole::Chef => 'chef',
        UserRole::Waiter => 'waiter',
        default => null,
    };

    if ($presenceRole === null) {
        return false;
    }

    return [
        'id' => (int) $user->id,
        'role' => $presenceRole,
        'label' => $role->label(),
    ];
}, ['guards' => ['web', 'admin']]);

/** R1.1 proof-of-connection channel — authenticated users only, minimal payload. */
Broadcast::channel('realtime.probe', function (?User $user): bool {
    return $user !== null;
}, ['guards' => ['web', 'admin']]);

/**
 * R1.6 dining session scoped channel.
 * Customer: own session only. Waiter/Operator/Admin: dining/order permissions.
 */
Broadcast::channel('dining-session.{sessionId}', function (?User $user, int|string $sessionId): bool {
    if ($user === null) {
        return false;
    }

    $session = DiningSession::query()->find($sessionId);
    if ($session === null) {
        return false;
    }

    if ($user->canOperateDining() || $user->canManageOrders() || $user->canOperateOrders()) {
        return true;
    }

    return $user->role === UserRole::Customer
        && $session->customer_id !== null
        && (int) $session->customer_id === (int) $user->getKey();
}, ['guards' => ['web', 'admin']]);

/**
 * R1.6 table scoped channel — staff dining operators only (not customers).
 */
Broadcast::channel('table.{tableId}', function (?User $user, int|string $tableId): bool {
    if ($user === null) {
        return false;
    }

    if (! ($user->canOperateDining() || $user->canManageOrders() || $user->canOperateOrders())) {
        return false;
    }

    return CafeTable::query()->whereKey($tableId)->exists();
}, ['guards' => ['web', 'admin']]);
