<?php

namespace App\Services\Tax;

use App\Models\Order;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;

class TaxCalculator implements TaxCalculatorInterface
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function currentConfig(): array
    {
        return $this->websiteSettings->taxConfig();
    }

    public function calculateForTaxableAmount(string $taxableAmount): TaxCalculation
    {
        $config = $this->currentConfig();
        $taxable = $this->normalizeMoney($taxableAmount);

        if (! $config['enabled'] || bccomp($config['percent'], '0', 2) <= 0) {
            return new TaxCalculation(
                enabled: false,
                label: $config['label'],
                percent: $this->normalizePercent($config['percent']),
                inclusive: $config['inclusive'],
                taxableAmount: $taxable,
                taxAmount: '0.00',
                cafeTotal: $taxable,
            );
        }

        $percent = $this->normalizePercent($config['percent']);

        if ($config['inclusive']) {
            // tax = taxable * rate / (100 + rate); round once to 2 dp
            $divisor = bcadd('100', $percent, 6);
            $rawTax = bcdiv(bcmul($taxable, $percent, 6), $divisor, 6);
            $taxAmount = $this->roundMoney($rawTax);

            return new TaxCalculation(
                enabled: true,
                label: $config['label'],
                percent: $percent,
                inclusive: true,
                taxableAmount: $taxable,
                taxAmount: $taxAmount,
                cafeTotal: $taxable,
            );
        }

        // Exclusive: tax = taxable * rate / 100; cafe total = taxable + tax
        $rawTax = bcdiv(bcmul($taxable, $percent, 6), '100', 6);
        $taxAmount = $this->roundMoney($rawTax);
        $cafeTotal = bcadd($taxable, $taxAmount, 2);

        return new TaxCalculation(
            enabled: true,
            label: $config['label'],
            percent: $percent,
            inclusive: false,
            taxableAmount: $taxable,
            taxAmount: $taxAmount,
            cafeTotal: $cafeTotal,
        );
    }

    public function fromOrderSnapshot(Order $order): TaxCalculation
    {
        $enabled = (bool) $order->tax_enabled_snapshot;
        $percent = $this->normalizePercent((string) ($order->tax_percent_snapshot ?? '0'));
        $taxable = $this->normalizeMoney((string) ($order->taxable_amount ?? $order->subtotal ?? '0'));
        $taxAmount = $this->normalizeMoney((string) ($order->tax_amount ?? '0'));
        $inclusive = (bool) $order->tax_inclusive_snapshot;
        $label = filled($order->tax_label_snapshot) ? (string) $order->tax_label_snapshot : 'GST';

        if (! $enabled) {
            return new TaxCalculation(
                enabled: false,
                label: $label,
                percent: $percent,
                inclusive: $inclusive,
                taxableAmount: $taxable,
                taxAmount: '0.00',
                cafeTotal: $this->normalizeMoney((string) $order->total_amount),
            );
        }

        $cafeTotal = $inclusive
            ? $taxable
            : bcadd($taxable, $taxAmount, 2);

        return new TaxCalculation(
            enabled: true,
            label: $label,
            percent: $percent,
            inclusive: $inclusive,
            taxableAmount: $taxable,
            taxAmount: $taxAmount,
            cafeTotal: $cafeTotal,
        );
    }

    public function payableTotal(TaxCalculation $tax, ?string $deliveryFeeAmount = null): string
    {
        $total = $tax->cafeTotal;

        if ($deliveryFeeAmount !== null && bccomp($this->normalizeMoney($deliveryFeeAmount), '0', 2) > 0) {
            $total = bcadd($total, $this->normalizeMoney($deliveryFeeAmount), 2);
        }

        return $total;
    }

    protected function normalizeMoney(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return $this->roundMoney((string) $value);
    }

    protected function normalizePercent(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return $this->roundMoney((string) $value);
    }

    /**
     * Half-up round to 2 decimal places using bcmath (avoids float drift).
     */
    protected function roundMoney(string $value): string
    {
        $negative = bccomp($value, '0', 8) < 0;
        $absolute = $negative ? bcmul($value, '-1', 8) : $value;
        $scaled = bcmul($absolute, '100', 4);
        $rounded = bcadd($scaled, '0.5', 0);

        $result = bcdiv($rounded, '100', 2);

        return $negative ? bcmul($result, '-1', 2) : $result;
    }
}
