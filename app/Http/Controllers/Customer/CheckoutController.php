<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutStoreRequest;
use App\Models\Order;
use App\Parsers\Checkout\CheckoutParserInterface;
use App\Services\Checkout\CheckoutServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutParserInterface $parser,
        protected CheckoutServiceInterface $checkoutService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $context = $this->checkoutService->getCheckoutContext($request->user());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('customer.cart.show')
                ->withErrors($exception->errors());
        }

        $checkoutToken = (string) $request->session()->get(
            config('coffee.checkout.session_token_key'),
            (string) Str::uuid(),
        );

        $request->session()->put(config('coffee.checkout.session_token_key'), $checkoutToken);

        return view('customer.checkout.show', [
            'cart' => $context['cart'],
            'summary' => $context['summary'],
            'checkoutToken' => $checkoutToken,
            'customer' => $request->user(),
        ]);
    }

    public function store(CheckoutStoreRequest $request): RedirectResponse
    {
        $order = $this->checkoutService->placeOrder(
            $request->user(),
            $this->parser->getTransferFromArrayData($request->validated()),
            $request->session()->get(config('coffee.checkout.session_token_key')),
        );

        $request->session()->forget(config('coffee.checkout.session_token_key'));

        return redirect()
            ->route('customer.checkout.confirmation', $order)
            ->with(
                'status',
                $order->isCashPayment()
                    ? 'Your order has been placed. Pay in cash when collecting or at the cafe.'
                    : 'Your order has been placed and is awaiting payment confirmation.',
            );
    }

    public function confirmation(Order $order): View
    {
        $this->authorize('view', $order);

        return view('customer.checkout.confirmation', [
            'order' => $order->load(['items', 'statusHistory']),
        ]);
    }
}
