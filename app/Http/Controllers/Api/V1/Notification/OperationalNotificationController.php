<?php

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OperationalNotificationResource;
use App\Models\OperationalNotificationRecipient;
use App\Models\User;
use App\Repositories\OperationalNotification\OperationalNotificationRepositoryInterface;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalNotificationController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected OperationalNotificationServiceInterface $notifications,
        protected OperationalNotificationRepositoryInterface $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $limit = min(50, max(1, (int) $request->integer('limit', 30)));

        $items = $this->notifications->listForUser($user, $limit, actionRequiredOnly: false);
        $counts = $this->notifications->countsForUser($user);

        return $this->respondWithCollection(
            $items,
            OperationalNotificationResource::class,
            'Notifications retrieved.',
            meta: $counts,
        );
    }

    public function actionRequired(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $limit = min(50, max(1, (int) $request->integer('limit', 30)));

        $items = $this->notifications->listForUser($user, $limit, actionRequiredOnly: true);
        $counts = $this->notifications->countsForUser($user);

        return $this->respondWithCollection(
            $items,
            OperationalNotificationResource::class,
            'Action-required notifications retrieved.',
            meta: $counts,
        );
    }

    public function delivered(Request $request, OperationalNotificationRecipient $recipient): JsonResponse
    {
        $recipient = $this->ownedRecipient($request, $recipient);

        return $this->respondWithResource(
            new OperationalNotificationResource($this->notifications->markDelivered($recipient)),
            'Notification marked delivered.',
        );
    }

    public function seen(Request $request, OperationalNotificationRecipient $recipient): JsonResponse
    {
        $recipient = $this->ownedRecipient($request, $recipient);

        return $this->respondWithResource(
            new OperationalNotificationResource($this->notifications->markSeen($recipient)),
            'Notification marked seen.',
        );
    }

    public function read(Request $request, OperationalNotificationRecipient $recipient): JsonResponse
    {
        $recipient = $this->ownedRecipient($request, $recipient);

        return $this->respondWithResource(
            new OperationalNotificationResource($this->notifications->markRead($recipient)),
            'Notification marked read.',
        );
    }

    public function acknowledge(Request $request, OperationalNotificationRecipient $recipient): JsonResponse
    {
        $recipient = $this->ownedRecipient($request, $recipient);

        return $this->respondWithResource(
            new OperationalNotificationResource($this->notifications->acknowledge($recipient)),
            'Notification acknowledged.',
        );
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    protected function ownedRecipient(Request $request, OperationalNotificationRecipient $recipient): OperationalNotificationRecipient
    {
        $user = $this->currentUser($request);
        $owned = $this->repository->findRecipientForUser((int) $recipient->getKey(), $user);
        // 404 avoids confirming another user's recipient id exists.
        abort_unless($owned !== null, 404);

        return $owned->loadMissing('notification');
    }
}
