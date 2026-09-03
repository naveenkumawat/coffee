<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\CampaignEligibleRequest;
use App\Http\Requests\Campaign\CampaignInteractionRequest;
use App\Models\User;
use App\Services\Campaign\CampaignEligibilityServiceInterface;
use Illuminate\Http\JsonResponse;

class CustomerCampaignController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CampaignEligibilityServiceInterface $campaigns,
    ) {}

    public function eligible(CampaignEligibleRequest $request): JsonResponse
    {
        $user = $request->user('web') ?? $request->user();
        $customer = $user instanceof User && $user->hasRole(UserRole::Customer) ? $user : null;

        $result = $this->campaigns->eligible($request->validated(), $customer);

        return $this->respondWithData($result, 'Eligible campaign retrieved.');
    }

    public function interact(CampaignInteractionRequest $request): JsonResponse
    {
        $user = $request->user('web') ?? $request->user();
        $customer = $user instanceof User && $user->hasRole(UserRole::Customer) ? $user : null;

        $result = $this->campaigns->recordInteraction($request->validated(), $customer);

        return $this->respondWithData($result, 'Campaign interaction recorded.');
    }
}
