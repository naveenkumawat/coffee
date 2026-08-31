<?php

namespace App\Services\Tax;

/**
 * Immutable tax calculation result for cafe taxable amounts (excludes third-party delivery fees).
 */
final class TaxCalculation
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $label,
        public readonly string $percent,
        public readonly bool $inclusive,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly string $cafeTotal,
    ) {}

    /**
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     percent: string,
     *     inclusive: bool,
     *     taxable_amount: string,
     *     amount: string,
     *     cafe_total: string
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'label' => $this->label,
            'percent' => $this->percent,
            'inclusive' => $this->inclusive,
            'taxable_amount' => $this->taxableAmount,
            'amount' => $this->taxAmount,
            'cafe_total' => $this->cafeTotal,
        ];
    }

    /**
     * Customer-safe order/API tax payload.
     *
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     percent: string,
     *     inclusive: bool,
     *     taxable_amount: string,
     *     amount: string
     * }
     */
    public function toCustomerArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'label' => $this->label,
            'percent' => $this->percent,
            'inclusive' => $this->inclusive,
            'taxable_amount' => $this->taxableAmount,
            'amount' => $this->taxAmount,
        ];
    }
}
