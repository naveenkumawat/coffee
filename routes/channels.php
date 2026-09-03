<?php

use App\Enums\UserRole;
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

/** R1.1 proof-of-connection channel — authenticated users only, minimal payload. */
Broadcast::channel('realtime.probe', function (?User $user): bool {
    return $user !== null;
}, ['guards' => ['web', 'admin']]);
