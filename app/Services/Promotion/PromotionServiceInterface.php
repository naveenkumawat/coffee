<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use App\Models\User;
use Carbon\CarbonInterface;

interface PromotionServiceInterface
{
    public function normalizeCode(?string $code): ?string;

    /**
     * @param  array{
     *     customer?: ?User,
     *     fulfilment?: mixed,
     *     promo_code?: ?string,
     *     items: list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>,
     *     at?: ?CarbonInterface
     * }  $context
     * @return array{
     *     discounts: list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>,
     *     discount_total: string,
     *     applied_promo_code: ?string,
     *     promo_error: ?string
     * }
     */
    public function evaluate(array $context): array;

    /**
     * @param  array{
     *     customer?: ?User,
     *     fulfilment?: mixed,
     *     promo_code?: ?string,
     *     items: list<array{product_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>,
     *     at?: ?CarbonInterface
     * }  $context
     * @return array{
     *     discounts: list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>,
     *     discount_total: string,
     *     applied_promo_code: ?string
     * }
     */
    public function assertAndEvaluateForCheckout(array $context): array;

    /**
     * @param  list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>  $discounts
     */
    public function assertUsageSlotsAvailable(array $discounts, ?User $customer): void;

    public function usageCount(Promotion $promotion): int;

    public function usageCountForCustomer(Promotion $promotion, User $customer): int;
}
