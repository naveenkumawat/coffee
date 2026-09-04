<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\Cart\CartServiceInterface;
use App\Services\Loyalty\LoyaltyRewardServiceInterface;
use App\Services\Loyalty\LoyaltyServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerLoyaltyController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected LoyaltyServiceInterface $loyalty,
        protected LoyaltyRewardServiceInterface $loyaltyRewards,
        protected CartServiceInterface $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $limit = (int) config('loyalty.history_limit', 20);
        $customer = $request->user();
        $payload = $this->loyalty->customerPayload($customer, $limit);

        $cart = $this->cartService->getForCustomer($customer);
        $summary = $this->cartService->summarize($cart);
        $merchandise = bcsub(
            (string) ($summary['subtotal'] ?? '0'),
            (string) ($summary['discount_total'] ?? '0'),
            2,
        );
        if (bccomp($merchandise, '0', 2) < 0) {
            $merchandise = '0.00';
        }

        $experience = $this->loyaltyRewards->customerExperiencePayload($customer, $merchandise);

        return $this->respondWithData([
            ...$payload,
            ...$experience,
        ], 'Loyalty account retrieved.');
    }

    public function rewards(Request $request): JsonResponse
    {
        $cart = $this->cartService->getForCustomer($request->user());
        $fulfilmentMethod = $request->query('fulfilment_method');
        $fulfilmentMethod = is_string($fulfilmentMethod) && $fulfilmentMethod !== '' ? $fulfilmentMethod : null;
        $summary = $this->cartService->summarize($cart, $fulfilmentMethod);

        $account = $this->loyalty->ensureAccount($request->user());
        $available = (int) $account->available_points;

        return $this->respondWithData([
            'available_points' => $available,
            'display_available_points' => max(0, $available),
            'has_points_debt' => $available < 0,
            'rewards' => $summary['loyalty_rewards'] ?? [],
            'available_now' => array_values(array_filter(
                $summary['loyalty_rewards'] ?? [],
                static fn (array $reward): bool => (bool) ($reward['eligible'] ?? false),
            )),
            'locked' => array_values(array_filter(
                $summary['loyalty_rewards'] ?? [],
                static fn (array $reward): bool => ! (bool) ($reward['eligible'] ?? false),
            )),
            'next_reward' => $summary['loyalty_next_reward'] ?? null,
            'selected_reward' => $summary['loyalty_reward'] ?? null,
            'loyalty_discount' => $summary['loyalty_discount'] ?? '0.00',
            'remaining_points_after_redemption' => $summary['loyalty_remaining_points_after'] ?? null,
        ], 'Loyalty rewards retrieved.');
    }
}
