<?php

namespace App\Services\Invoice;

/**
 * Canonical invoice payload built from order snapshots + website settings.
 *
 * @phpstan-type InvoiceLine array{
 *     product_name: string,
 *     variant_name: ?string,
 *     quantity: int,
 *     unit_price: string,
 *     line_total: string
 * }
 */
final class OrderInvoiceData
{
    /**
     * @param  list<InvoiceLine>  $lines
     */
    public function __construct(
        public readonly string $invoiceNumber,
        public readonly string $orderNumber,
        public readonly string $cafeName,
        public readonly ?string $cafeSlogan,
        public readonly ?string $cafePhone,
        public readonly ?string $cafeEmail,
        public readonly ?string $cafeAddress,
        public readonly ?string $legalBusinessName,
        public readonly ?string $gstin,
        public readonly string $placedAtLabel,
        public readonly ?string $paidAtLabel,
        public readonly string $customerName,
        public readonly ?string $customerPhone,
        public readonly ?string $customerEmail,
        public readonly string $fulfilmentLabel,
        public readonly string $fulfilmentCode,
        public readonly ?string $tableLabel,
        public readonly ?string $deliveryContactName,
        public readonly ?string $deliveryPhone,
        public readonly ?string $deliveryAddress,
        public readonly array $lines,
        public readonly string $subtotal,
        public readonly string $discountTotal,
        public readonly bool $taxEnabled,
        public readonly string $taxLabel,
        public readonly string $taxPercent,
        public readonly bool $taxInclusive,
        public readonly string $taxableAmount,
        public readonly string $taxAmount,
        public readonly ?string $deliveryFeeAmount,
        public readonly string $totalAmount,
        public readonly string $paymentMethodLabel,
        public readonly string $paymentStatusLabel,
        public readonly string $footerMessage,
        public readonly string $downloadBasename,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'invoice_number' => $this->invoiceNumber,
            'order_number' => $this->orderNumber,
            'cafe_name' => $this->cafeName,
            'cafe_slogan' => $this->cafeSlogan,
            'cafe_phone' => $this->cafePhone,
            'cafe_email' => $this->cafeEmail,
            'cafe_address' => $this->cafeAddress,
            'legal_business_name' => $this->legalBusinessName,
            'gstin' => $this->gstin,
            'placed_at_label' => $this->placedAtLabel,
            'paid_at_label' => $this->paidAtLabel,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_email' => $this->customerEmail,
            'fulfilment_label' => $this->fulfilmentLabel,
            'fulfilment_code' => $this->fulfilmentCode,
            'table_label' => $this->tableLabel,
            'delivery_contact_name' => $this->deliveryContactName,
            'delivery_phone' => $this->deliveryPhone,
            'delivery_address' => $this->deliveryAddress,
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discountTotal,
            'tax_enabled' => $this->taxEnabled,
            'tax_label' => $this->taxLabel,
            'tax_percent' => $this->taxPercent,
            'tax_inclusive' => $this->taxInclusive,
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->taxAmount,
            'delivery_fee_amount' => $this->deliveryFeeAmount,
            'total_amount' => $this->totalAmount,
            'payment_method_label' => $this->paymentMethodLabel,
            'payment_status_label' => $this->paymentStatusLabel,
            'footer_message' => $this->footerMessage,
            'download_basename' => $this->downloadBasename,
        ];
    }
}
