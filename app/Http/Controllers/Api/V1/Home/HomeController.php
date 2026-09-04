<?php

namespace App\Http\Controllers\Api\V1\Home;

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\HomeShowRequest;
use App\Models\User;
use App\Services\Merchandising\MerchandisingServiceInterface;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected MerchandisingServiceInterface $merchandising,
    ) {}

    public function show(HomeShowRequest $request): JsonResponse
    {
        $user = $request->user('web') ?? $request->user();
        $customer = $user instanceof User && $user->hasRole(UserRole::Customer) ? $user : null;

        $payload = $this->merchandising->landingPayload($request->validated(), $customer);

        return $this->respondWithData($payload, 'Homepage sections retrieved.');
    }
}
