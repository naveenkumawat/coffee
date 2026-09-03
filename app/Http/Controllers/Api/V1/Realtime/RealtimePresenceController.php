<?php

namespace App\Http\Controllers\Api\V1\Realtime;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\OperationalNotification\OperationalBusinessNotificationPublisher;
use App\Services\Realtime\RealtimePresenceService;
use App\Services\Realtime\RealtimePresenceServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimePresenceController extends Controller
{
    public function __construct(
        protected RealtimePresenceServiceInterface $presence,
        protected OperationalBusinessNotificationPublisher $publisher,
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || $this->presence->presenceKeyForUser($user) === null) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $this->presence->heartbeat($user);
        $this->publisher->handleStaffRoleCameOnline($user);

        return response()->json([
            'data' => [
                'ok' => true,
                'ttl_seconds' => RealtimePresenceService::TTL_SECONDS,
                'interval_seconds' => RealtimePresenceService::HEARTBEAT_INTERVAL_SECONDS,
            ],
        ]);
    }

    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $this->presence->leave($user);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role;

        $allowed = $role instanceof UserRole && (
            $role->canAccessAdministratorPanel()
            || $role === UserRole::Operator
        );

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => [
                'roles' => $this->presence->summaryCounts(),
                'advisory' => true,
            ],
        ]);
    }
}
