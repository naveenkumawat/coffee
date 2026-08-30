<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use App\Repositories\Order\OrderRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected OrderRepositoryInterface $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->integer('per_page', 10)));

        return $this->respondWithPaginator(
            $this->orders->paginateForCustomer($request->user(), $perPage),
            OrderResource::class,
            'Customer orders retrieved.',
        );
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->respondWithResource(
            new OrderResource($order->load(['items', 'statusHistory'])),
            'Customer order detail retrieved.',
        );
    }
}
