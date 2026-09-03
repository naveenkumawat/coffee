<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendation\RecommendationIndexRequest;
use App\Models\User;
use App\Services\Recommendation\RecommendationServiceInterface;
use Illuminate\Http\JsonResponse;

class CustomerRecommendationController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected RecommendationServiceInterface $recommendations,
    ) {}

    public function index(RecommendationIndexRequest $request): JsonResponse
    {
        $user = $request->user('web') ?? $request->user();
        $customer = $user instanceof User && $user->hasRole(UserRole::Customer) ? $user : null;

        $result = $this->recommendations->recommend($request->validated(), $customer);

        return $this->respondWithData($result, 'Recommendations retrieved.');
    }
}
