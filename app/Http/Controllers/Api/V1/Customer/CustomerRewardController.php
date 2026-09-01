<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\CartReferralRewardRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\Cart\CartServiceInterface;
use App\Services\Referral\ReferralServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerRewardController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CartServiceInterface $cartService,
        protected ReferralServiceInterface $referrals,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rewards = $this->referrals->activeRewardsFor($request->user());

        return $this->respondWithData([
            'rewards' => $rewards->map(static fn ($reward): array => [
                'id' => (int) $reward->getKey(),
                'reward_type' => $reward->reward_type?->value,
                'title' => $reward->displayTitle(),
                'coupon_code' => $reward->coupon_code,
                'expires_at' => $reward->expires_at?->toIso8601String(),
                'product_id' => $reward->product_id,
                'variant_id' => $reward->variant_id,
                'quantity' => $reward->quantity,
            ])->values()->all(),
        ], 'Active rewards retrieved.');
    }

    public function addFreeDrinkToCart(CartReferralRewardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! isset($validated['reward_id'])) {
            throw ValidationException::withMessages([
                'reward_id' => 'Select a free drink reward.',
            ]);
        }

        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);

        $cart = $this->cartService->addFreeDrinkRewardToCart(
            $request->user(),
            (int) $validated['reward_id'],
            $validated['fulfilment_method'] ?? null,
        );

        return $this->respondWithResource(
            new CartResource($cart),
            'Free drink reward applied.',
            200,
            [
                'summary' => $this->cartService->summarize($cart, $validated['fulfilment_method'] ?? null),
            ],
        );
    }

    public function applyCoupon(CartReferralRewardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! isset($validated['referral_coupon'])) {
            throw ValidationException::withMessages([
                'referral_coupon' => 'Enter a referral reward code.',
            ]);
        }

        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);

        $cart = $this->cartService->applyReferralCouponReward(
            $request->user(),
            (string) $validated['referral_coupon'],
            $validated['fulfilment_method'] ?? null,
        );

        return $this->respondWithResource(
            new CartResource($cart),
            'Referral coupon applied.',
            200,
            [
                'summary' => $this->cartService->summarize($cart, $validated['fulfilment_method'] ?? null),
            ],
        );
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->getForCustomer($request->user());
        $this->authorize('view', $cart);

        $cart = $this->cartService->clearReferralRewards($request->user());
        $fulfilmentMethod = $request->query('fulfilment_method');
        $fulfilmentMethod = is_string($fulfilmentMethod) && $fulfilmentMethod !== '' ? $fulfilmentMethod : null;

        return $this->respondWithResource(
            new CartResource($cart),
            'Referral rewards cleared.',
            200,
            [
                'summary' => $this->cartService->summarize($cart, $fulfilmentMethod),
            ],
        );
    }
}
