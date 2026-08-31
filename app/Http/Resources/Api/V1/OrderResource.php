<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;

        return [
            'id' => $order->getKey(),
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'status_label' => $order->customerStatusLabel(),
            'fulfilment_method' => $order->fulfilment_method?->value,
            'fulfilment_method_label' => $order->fulfilment_method?->label(),
            'cafe_table_id' => $order->cafe_table_id,
            'table_name' => $order->tableDisplayLabel(),
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'pickup_name' => $order->pickup_name,
            'pickup_phone' => $order->pickup_phone,
            'customer_notes' => $order->customer_notes,
            'pickup_notes' => $order->pickup_notes,
            'delivery_address' => $order->delivery_address,
            'delivery_phone' => $order->delivery_phone,
            'delivery_contact_name' => $order->delivery_contact_name,
            'delivery_notes' => $order->delivery_notes,
            'delivery_provider' => $order->delivery_provider,
            'delivery_fee_amount' => $order->delivery_fee_amount,
            'delivery_tracking_reference' => $order->delivery_tracking_reference,
            'delivery_status' => $order->delivery_status,
            'delivery_disclaimer' => $order->isDelivery()
                ? app(WebsiteSettingServiceInterface::class)->deliveryDisclaimer()
                : null,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'total_amount' => $order->total_amount,
            'payment_method' => $order->payment_method?->value ?? 'manual',
            'payment_status' => $order->payment_status?->value,
            'payment_status_label' => $order->payment_status?->label(),
            'payment_reference' => $order->payment_reference,
            'payment_proof' => [
                'uploaded' => $order->hasPaymentProof(),
                'uploaded_at' => $order->payment_proof_uploaded_at?->toIso8601String(),
                'mime' => $order->payment_proof_mime,
                'size' => $order->payment_proof_size,
                'can_upload' => $order->canUploadPaymentProof(),
                'rejection_notes' => $order->payment_proof_rejection_notes,
            ],
            'placed_at' => $order->placed_at?->toIso8601String(),
            'payment_confirmed_at' => $order->payment_confirmed_at?->toIso8601String(),
            'accepted_at' => $order->accepted_at?->toIso8601String(),
            'preparing_at' => $order->preparing_at?->toIso8601String(),
            'ready_for_pickup_at' => $order->ready_for_pickup_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'rejected_at' => $order->rejected_at?->toIso8601String(),
            'items' => OrderItemResource::collection($order->items),
            'status_timeline' => OrderStatusHistoryResource::collection(
                $order->statusHistory->each->setRelation('order', $order),
            ),
            'invoice_available' => app(OrderInvoiceServiceInterface::class)->isAvailable($order),
        ];
    }
}
