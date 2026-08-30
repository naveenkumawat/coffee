<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
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
            'status_label' => $order->status?->label(),
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'pickup_name' => $order->pickup_name,
            'pickup_phone' => $order->pickup_phone,
            'customer_notes' => $order->customer_notes,
            'pickup_notes' => $order->pickup_notes,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'total_amount' => $order->total_amount,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'payment_confirmed_at' => $order->payment_confirmed_at?->toIso8601String(),
            'accepted_at' => $order->accepted_at?->toIso8601String(),
            'preparing_at' => $order->preparing_at?->toIso8601String(),
            'ready_for_pickup_at' => $order->ready_for_pickup_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'rejected_at' => $order->rejected_at?->toIso8601String(),
            'items' => OrderItemResource::collection($order->items),
            'status_timeline' => OrderStatusHistoryResource::collection($order->statusHistory),
        ];
    }
}
