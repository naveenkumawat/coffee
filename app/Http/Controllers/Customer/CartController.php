<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\CartItemStoreRequest;
use App\Http\Requests\Cart\CartItemUpdateRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Parsers\Cart\CartParserInterface;
use App\Services\Cart\CartServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartParserInterface $parser,
        protected CartServiceInterface $cartService,
    ) {}

    public function show(Request $request): View
    {
        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);

        return view('customer.cart.show', [
            'cart' => $cart,
            'summary' => $this->cartService->summarize($cart),
        ]);
    }

    public function store(CartItemStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Cart::class);
        $this->cartService->addItem(
            $request->user(),
            $this->parser->getTransferFromArrayData($request->validated()),
        );

        return redirect()
            ->route('customer.cart.show')
            ->with('status', 'Item added to your cart.');
    }

    public function update(CartItemUpdateRequest $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('update', $cartItem);
        $this->cartService->updateItem(
            $request->user(),
            $cartItem,
            $this->parser->getTransferFromArrayData($request->validated()),
        );

        return redirect()
            ->route('customer.cart.show')
            ->with('status', 'Cart item updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        $this->authorize('delete', $cartItem);
        $this->cartService->removeItem($request->user(), $cartItem);

        return redirect()
            ->route('customer.cart.show')
            ->with('status', 'Item removed from your cart.');
    }

    public function clear(Request $request): RedirectResponse
    {
        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);
        $this->cartService->clear($request->user());

        return redirect()
            ->route('customer.cart.show')
            ->with('status', 'Your cart has been cleared.');
    }
}
