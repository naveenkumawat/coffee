<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\UserRole;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Behaviour\MergeBehaviourVisitorRequest;
use App\Http\Requests\Behaviour\StoreBehaviourEventRequest;
use App\Models\User;
use App\Services\Behaviour\BehaviourEventServiceInterface;
use Illuminate\Http\JsonResponse;

class CustomerBehaviourEventController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected BehaviourEventServiceInterface $behaviour,
    ) {}

    public function store(StoreBehaviourEventRequest $request): JsonResponse
    {
        $user = $request->user('web') ?? $request->user();
        $customer = $user instanceof User && $user->hasRole(UserRole::Customer) ? $user : null;

        $result = $this->behaviour->ingestClientEvent($request->validated(), $customer);

        return $this->respondWithData($result, 'Behaviour event processed.');
    }

    public function merge(MergeBehaviourVisitorRequest $request): JsonResponse
    {
        $customer = $request->user('web') ?? $request->user();

        if (! $customer instanceof User || ! $customer->hasRole(UserRole::Customer)) {
            abort(403);
        }

        $result = $this->behaviour->mergeVisitorToCustomer(
            (string) $request->validated('visitor_key'),
            $customer,
        );

        return $this->respondWithData($result, 'Visitor behaviour association processed.');
    }
}
