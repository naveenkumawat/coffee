<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\Loyalty\LoyaltyServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLoyaltyController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected LoyaltyServiceInterface $loyalty,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $limit = (int) config('loyalty.history_limit', 20);
        $payload = $this->loyalty->customerPayload($request->user(), $limit);

        return $this->respondWithData($payload, 'Loyalty account retrieved.');
    }
}
