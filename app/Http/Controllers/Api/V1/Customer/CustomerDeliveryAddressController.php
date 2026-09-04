<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerDeliveryAddress\CustomerDeliveryAddressStoreRequest;
use App\Http\Requests\CustomerDeliveryAddress\CustomerDeliveryAddressUpdateRequest;
use App\Http\Resources\Api\V1\CustomerDeliveryAddressResource;
use App\Models\CustomerDeliveryAddress;
use App\Services\CustomerDeliveryAddress\CustomerDeliveryAddressServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDeliveryAddressController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CustomerDeliveryAddressServiceInterface $addresses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = $this->addresses->listForCustomer($request->user());

        return $this->respondWithCollection(
            $rows,
            CustomerDeliveryAddressResource::class,
            'Delivery addresses retrieved.',
        );
    }

    public function store(CustomerDeliveryAddressStoreRequest $request): JsonResponse
    {
        $address = $this->addresses->store($request->user(), $request->validated());

        return $this->respondWithResource(
            new CustomerDeliveryAddressResource($address),
            'Delivery address saved.',
            201,
        );
    }

    public function update(CustomerDeliveryAddressUpdateRequest $request, CustomerDeliveryAddress $deliveryAddress): JsonResponse
    {
        $address = $this->addresses->update($request->user(), $deliveryAddress, $request->validated());

        return $this->respondWithResource(
            new CustomerDeliveryAddressResource($address),
            'Delivery address updated.',
        );
    }

    public function destroy(Request $request, CustomerDeliveryAddress $deliveryAddress): JsonResponse
    {
        $this->addresses->delete($request->user(), $deliveryAddress);

        return $this->respondWithData(null, 'Delivery address deleted.');
    }

    public function makeDefault(Request $request, CustomerDeliveryAddress $deliveryAddress): JsonResponse
    {
        $address = $this->addresses->makeDefault($request->user(), $deliveryAddress);

        return $this->respondWithResource(
            new CustomerDeliveryAddressResource($address),
            'Default delivery address updated.',
        );
    }
}
