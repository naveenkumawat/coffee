<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutStoreRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Http\Resources\Api\V1\OrderResource;
use App\Parsers\Checkout\CheckoutParserInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\Checkout\CheckoutServiceInterface;
use App\Services\Payment\PaymentEligibilityServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerCheckoutController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CheckoutParserInterface $parser,
        protected CheckoutServiceInterface $checkoutService,
        protected OrderRepositoryInterface $orders,
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected PaymentEligibilityServiceInterface $paymentEligibility,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $fulfilmentMethod = $request->query('fulfilment_method');
        $fulfilmentMethod = is_string($fulfilmentMethod) && $fulfilmentMethod !== '' ? $fulfilmentMethod : null;
        $context = $this->checkoutService->getCheckoutContext($request->user(), $fulfilmentMethod);
        $checkoutToken = $this->rememberCheckoutToken($request->user()->getKey());

        return $this->respondWithResource(
            new CartResource($context['cart']),
            'Checkout summary retrieved.',
            200,
            [
                'summary' => $context['summary'],
                'checkout_token' => $checkoutToken,
                'customer' => [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone,
                ],
                'fulfilment' => [
                    'methods' => [
                        ['value' => 'takeaway', 'label' => 'Takeaway'],
                        ['value' => 'delivery', 'label' => 'Delivery'],
                    ],
                    'pickup_address' => $this->websiteSettings->customerContent()['business']['address'] ?? null,
                    'delivery_disclaimer' => $this->websiteSettings->deliveryDisclaimer(),
                    'dining_enabled' => $this->websiteSettings->diningEnabled(),
                    'dine_in_enabled' => $this->websiteSettings->diningEnabled(),
                ],
                'payment_methods' => $this->paymentEligibility->methodsByFulfilment($request->user()),
                'payment' => $this->paymentInstructions(),
            ],
        );
    }

    public function store(CheckoutStoreRequest $request): JsonResponse
    {
        $checkoutToken = (string) $request->validated('checkout_token');
        $existingOrder = $this->orders->findByCheckoutToken($checkoutToken);

        if ($existingOrder && (int) $existingOrder->customer_id === (int) $request->user()->getKey()) {
            return $this->respondWithResource(
                new OrderResource($existingOrder->loadMissing(['items.addOns', 'statusHistory', 'promotions', 'rewardRedemptions'])),
                'Order already exists for this checkout token.',
                200,
                [
                    'payment' => $this->paymentInstructions(),
                ],
            );
        }

        $expectedCheckoutToken = Cache::get($this->checkoutCacheKey($request->user()->getKey()));

        if (! filled($expectedCheckoutToken)) {
            throw ValidationException::withMessages([
                'checkout' => 'This checkout session has expired. Please refresh the summary and try again.',
            ]);
        }

        $order = $this->checkoutService->placeOrder(
            $request->user(),
            $this->parser->getTransferFromArrayData($request->validated()),
            is_string($expectedCheckoutToken) ? $expectedCheckoutToken : null,
        );

        Cache::forget($this->checkoutCacheKey($request->user()->getKey()));

        return $this->respondWithResource(
            new OrderResource($order->loadMissing(['items.addOns', 'statusHistory', 'promotions', 'rewardRedemptions'])),
            $order->isCashPayment()
                ? 'Order placed successfully.'
                : 'Order placed successfully and is awaiting payment confirmation.',
            201,
            [
                'payment' => $order->isCashPayment() ? null : $this->paymentInstructions(),
            ],
        );
    }

    protected function rememberCheckoutToken(int $customerId): string
    {
        $cacheKey = $this->checkoutCacheKey($customerId);
        $checkoutToken = (string) (Cache::get($cacheKey) ?: Str::uuid());
        Cache::put($cacheKey, $checkoutToken, now()->addMinutes(30));

        return $checkoutToken;
    }

    protected function checkoutCacheKey(int $customerId): string
    {
        return config('coffee.checkout.api_token_cache_prefix').$customerId;
    }

    protected function paymentInstructions(): array
    {
        return $this->websiteSettings->paymentInstructions();
    }
}
