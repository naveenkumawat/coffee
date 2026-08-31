<?php

namespace App\Services\Tax;

use App\Models\Order;

interface TaxCalculatorInterface
{
    /**
     * Live settings configuration (not order snapshots).
     *
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     percent: string,
     *     inclusive: bool,
     *     gstin: ?string,
     *     legal_business_name: ?string
     * }
     */
    public function currentConfig(): array;

    /**
     * Calculate tax for a cafe taxable amount (subtotal − discount). Delivery fees are never taxed here.
     */
    public function calculateForTaxableAmount(string $taxableAmount): TaxCalculation;

    /**
     * Rebuild a calculation view from an order's stored tax snapshot.
     */
    public function fromOrderSnapshot(Order $order): TaxCalculation;

    /**
     * Final payable cafe total including exclusive tax, then optional untaxed delivery fee.
     */
    public function payableTotal(TaxCalculation $tax, ?string $deliveryFeeAmount = null): string;
}
