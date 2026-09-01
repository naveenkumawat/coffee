<?php

namespace App\Services\Invoice;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Tax\TaxCalculatorInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OrderInvoiceService implements OrderInvoiceServiceInterface
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected TaxCalculatorInterface $taxCalculator,
    ) {}

    public function isAvailable(Order $order): bool
    {
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            return false;
        }

        if ($order->payment_status === PaymentStatus::Confirmed) {
            return true;
        }

        return in_array($order->status, [
            OrderStatus::PaymentConfirmed,
            OrderStatus::Accepted,
            OrderStatus::Preparing,
            OrderStatus::ReadyForPickup,
            OrderStatus::Completed,
        ], true);
    }

    public function build(Order $order): OrderInvoiceData
    {
        $order->loadMissing(['items', 'rewardRedemptions']);

        $content = $this->websiteSettings->customerContent();
        $business = $content['business'] ?? [];
        $hero = $content['hero'] ?? [];
        $taxLive = $this->websiteSettings->taxConfig();
        $tax = $this->taxCalculator->fromOrderSnapshot($order);

        $cafeName = filled($business['name'] ?? null)
            ? (string) $business['name']
            : (string) config('app.name', 'The88Coffees');

        $lines = $order->items
            ->map(function (OrderItem $item): array {
                return [
                    'product_name' => (string) $item->product_name,
                    'variant_name' => filled($item->variant_name) ? (string) $item->variant_name : null,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                    'line_total' => number_format((float) $item->line_subtotal, 2, '.', ''),
                ];
            })
            ->values()
            ->all();

        $deliveryFee = $order->delivery_fee_amount !== null
            && bccomp((string) $order->delivery_fee_amount, '0', 2) > 0
            ? number_format((float) $order->delivery_fee_amount, 2, '.', '')
            : null;

        $invoiceNumber = (string) $order->order_number;
        $about = filled($business['about_short'] ?? null)
            ? (string) $business['about_short']
            : null;

        $freeDrinkBenefit = $order->rewardRedemptions
            ->filter(fn ($redemption): bool => $redemption->reward_type?->value === 'free_drink')
            ->reduce(
                fn (string $carry, $redemption): string => bcadd($carry, (string) $redemption->benefit_amount, 2),
                '0.00',
            );

        return new OrderInvoiceData(
            invoiceNumber: $invoiceNumber,
            orderNumber: $invoiceNumber,
            cafeName: $cafeName,
            cafeSlogan: filled($hero['subtitle'] ?? null) ? (string) $hero['subtitle'] : null,
            cafePhone: filled($business['phone'] ?? null) ? (string) $business['phone'] : null,
            cafeEmail: filled($business['email'] ?? null) ? (string) $business['email'] : null,
            cafeAddress: filled($business['address'] ?? null) ? (string) $business['address'] : null,
            legalBusinessName: $taxLive['legal_business_name'],
            gstin: $taxLive['gstin'],
            placedAtLabel: $order->placed_at?->format('d M Y, h:i A')
                ?? $order->created_at?->format('d M Y, h:i A')
                ?? '—',
            paidAtLabel: $order->payment_confirmed_at?->format('d M Y, h:i A'),
            customerName: (string) ($order->customer_name ?: 'Guest'),
            customerPhone: filled($order->customer_phone) ? (string) $order->customer_phone : null,
            customerEmail: filled($order->customer_email) ? (string) $order->customer_email : null,
            fulfilmentLabel: strtoupper((string) ($order->fulfilment_method?->label() ?? 'Takeaway')),
            fulfilmentCode: (string) ($order->fulfilment_method?->value ?? 'takeaway'),
            tableLabel: $order->isDineIn() ? $order->tableDisplayLabel() : null,
            deliveryContactName: $order->isDelivery()
                ? (filled($order->delivery_contact_name) ? (string) $order->delivery_contact_name : null)
                : null,
            deliveryPhone: $order->isDelivery()
                ? (filled($order->delivery_phone) ? (string) $order->delivery_phone : null)
                : null,
            deliveryAddress: $order->isDelivery()
                ? (filled($order->delivery_address) ? (string) $order->delivery_address : null)
                : null,
            lines: $lines,
            subtotal: number_format((float) $order->subtotal, 2, '.', ''),
            discountTotal: number_format((float) $order->discount_total, 2, '.', ''),
            freeDrinkBenefit: number_format((float) $freeDrinkBenefit, 2, '.', ''),
            taxEnabled: $tax->enabled,
            taxLabel: $tax->label,
            taxPercent: $tax->percent,
            taxInclusive: $tax->inclusive,
            taxableAmount: $tax->taxableAmount,
            taxAmount: $tax->taxAmount,
            deliveryFeeAmount: $deliveryFee,
            totalAmount: number_format((float) $order->total_amount, 2, '.', ''),
            paymentMethodLabel: (string) ($order->payment_method?->label() ?? 'Manual'),
            paymentStatusLabel: (string) ($order->payment_status?->label() ?? 'Pending'),
            footerMessage: $about ?: 'Thank you for your order.',
            downloadBasename: $this->downloadBasename($cafeName, $invoiceNumber),
        );
    }

    public function downloadPdf(Order $order): Response
    {
        $invoice = $this->build($order);

        $pdf = Pdf::loadView('invoices.a4', [
            'invoice' => $invoice,
            'mode' => 'pdf',
        ])->setPaper('a4');

        return $pdf->download($invoice->downloadBasename.'.pdf');
    }

    public function pdfBinary(Order $order): string
    {
        $invoice = $this->build($order);

        return Pdf::loadView('invoices.a4', [
            'invoice' => $invoice,
            'mode' => 'pdf',
        ])->setPaper('a4')->output();
    }

    public function normalizeThermalWidth(string|int|null $widthMm): string
    {
        return ((string) $widthMm) === '58' ? '58' : '80';
    }

    protected function downloadBasename(string $cafeName, string $orderNumber): string
    {
        $brand = Str::of($cafeName)
            ->replaceMatches('/[^A-Za-z0-9]+/', '')
            ->trim()
            ->toString();

        if ($brand === '') {
            $brand = 'Invoice';
        }

        $safeOrder = Str::of($orderNumber)
            ->replaceMatches('/[^A-Za-z0-9\-_]+/', '-')
            ->trim('-')
            ->toString();

        return $brand.'-'.$safeOrder;
    }
}
