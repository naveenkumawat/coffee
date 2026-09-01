<?php

namespace App\Http\Controllers\Api\V1\CafeAvailability;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use Illuminate\Http\JsonResponse;

class CafeAvailabilityController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CafeAvailabilityServiceInterface $availability,
    ) {}

    public function show(): JsonResponse
    {
        return $this->respondWithData(
            $this->availability->publicStatus()->toPublicArray(),
            'Café availability retrieved.',
        );
    }
}
