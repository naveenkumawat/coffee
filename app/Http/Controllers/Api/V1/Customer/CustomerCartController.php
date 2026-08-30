<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\CartItemStoreRequest;
use App\Http\Requests\Cart\CartItemUpdateRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Parsers\Cart\CartParserInterface;
use App\Services\Cart\CartServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerCartController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CartParserInterface $parser,
        protected CartServiceInterface $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);

        return $this->respondWithResource(
            new CartResource($cart),
            'Cart retrieved.',
            200,
            [
                'summary' => $this->cartService->summarize($cart),
            ],
        );
    }

    public function count(Request $request): JsonResponse
    {
        return $this->respondWithData([
            'count' => $this->cartService->count($request->user()),
        ], 'Cart count retrieved.');
    }

    public function store(CartItemStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Cart::class);

        $cart = $this->cartService->addItem(
            $request->user(),
            $this->parser->getTransferFromArrayData($request->validated()),
        );

        return $this->respondWithResource(
            new CartResource($cart),
            'Item added to cart.',
            201,
            [
                'summary' => $this->cartService->summarize($cart),
            ],
        );
    }

    public function update(CartItemUpdateRequest $request, CartItem $cartItem): JsonResponse
    {
        $this->authorize('update', $cartItem);

        $cart = $this->cartService->updateItem(
            $request->user(),
            $cartItem,
            $this->parser->getTransferFromArrayData($request->validated()),
        );

        return $this->respondWithResource(
            new CartResource($cart),
            'Cart item updated.',
            200,
            [
                'summary' => $this->cartService->summarize($cart),
            ],
        );
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $this->authorize('delete', $cartItem);

        $cart = $this->cartService->removeItem($request->user(), $cartItem);

        return $this->respondWithResource(
            new CartResource($cart),
            'Item removed from cart.',
            200,
            [
                'summary' => $this->cartService->summarize($cart),
            ],
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);

        $cart = $this->cartService->clear($request->user());

        return $this->respondWithResource(
            new CartResource($cart),
            'Cart cleared successfully.',
            200,
            [
                'summary' => $this->cartService->summarize($cart),
            ],
        );
    }
}
