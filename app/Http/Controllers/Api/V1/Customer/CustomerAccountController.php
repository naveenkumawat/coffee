<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CustomerPasswordUpdateRequest;
use App\Http\Requests\Customer\CustomerProfileUpdateRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Parsers\User\UserParserInterface;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAccountController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected UserParserInterface $parser,
        protected UserRepositoryInterface $users,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respondWithResource(
            new CustomerResource($request->user()),
            'Authenticated customer retrieved.',
        );
    }

    public function updateProfile(CustomerProfileUpdateRequest $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validated() + [
            'role' => $customer->role->value,
            'is_active' => (bool) $customer->is_active,
        ];

        $customer = DB::transaction(fn () => $this->users->update(
            $customer,
            $this->parser->getTransferFromArrayData($data)->toArray(),
        ));

        return $this->respondWithResource(
            new CustomerResource($customer),
            'Profile updated successfully.',
        );
    }

    public function updatePassword(CustomerPasswordUpdateRequest $request): JsonResponse
    {
        $customer = DB::transaction(function () use ($request) {
            return $this->users->update($request->user(), [
                'password' => (string) $request->validated('password'),
            ]);
        });

        return $this->respondWithResource(
            new CustomerResource($customer),
            'Password updated successfully.',
        );
    }
}
