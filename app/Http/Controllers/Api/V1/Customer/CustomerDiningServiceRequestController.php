<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dining\DiningServiceRequestResource;
use App\Models\DiningServiceRequest;
use App\Models\DiningSession;
use App\Services\Dining\DiningServiceRequestServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerDiningServiceRequestController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected DiningServiceRequestServiceInterface $serviceRequests,
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function store(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('create', [DiningServiceRequest::class, $session]);

        if (! $this->websiteSettings->diningEnabled()) {
            throw ValidationException::withMessages([
                'dining' => 'Dining is disabled.',
            ]);
        }

        $existing = $this->serviceRequests->currentForSession($session);
        $serviceRequest = $this->serviceRequests->createOrderAssistance($session, $request->user());
        $reused = $existing !== null && (int) $existing->getKey() === (int) $serviceRequest->getKey();

        return $this->respondWithResource(
            new DiningServiceRequestResource($serviceRequest),
            $reused ? 'Waiter already called.' : 'We’ve received your request.',
            $reused ? 200 : 201,
        );
    }

    public function current(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $serviceRequest = $this->serviceRequests->currentForSession($session);

        if ($serviceRequest === null) {
            return $this->respondWithData(null, 'No open waiter request.');
        }

        $this->authorize('view', $serviceRequest);

        return $this->respondWithResource(
            new DiningServiceRequestResource($serviceRequest),
            'Current waiter request retrieved.',
        );
    }

    public function cancel(Request $request, DiningServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('cancel', $serviceRequest);

        $updated = $this->serviceRequests->cancel($serviceRequest, $request->user());

        return $this->respondWithResource(
            new DiningServiceRequestResource($updated),
            'Waiter request cancelled.',
        );
    }
}
