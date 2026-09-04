<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dining\DiningServiceRequestResource;
use App\Models\DiningServiceRequest;
use App\Services\Dining\DiningServiceRequestServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaiterDiningServiceRequestController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected DiningServiceRequestServiceInterface $serviceRequests,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DiningServiceRequest::class);

        $items = $this->serviceRequests->openRequestsForWaiter($request->user());

        return $this->respondWithData([
            'pending_count' => $this->serviceRequests->pendingCountForWaiter($request->user()),
            'requests' => DiningServiceRequestResource::collection($items)->resolve(),
        ], 'Service requests retrieved.');
    }

    public function claim(Request $request, DiningServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('claim', $serviceRequest);

        $updated = $this->serviceRequests->claim($serviceRequest, $request->user());

        return $this->respondWithResource(
            new DiningServiceRequestResource($updated),
            'Service request accepted.',
        );
    }

    public function complete(Request $request, DiningServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('complete', $serviceRequest);

        $updated = $this->serviceRequests->complete($serviceRequest, $request->user(), 'waiter_marked_done');

        return $this->respondWithResource(
            new DiningServiceRequestResource($updated),
            'Service request completed.',
        );
    }
}
