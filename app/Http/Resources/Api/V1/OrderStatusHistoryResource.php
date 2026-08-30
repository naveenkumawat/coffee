<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderStatusHistory $history */
        $history = $this->resource;
        /** @var Order|null $order */
        $order = $history->relationLoaded('order') ? $history->order : null;

        return [
            'id' => $history->getKey(),
            'from_status' => $history->from_status?->value,
            'from_status_label' => $order
                ? $order->customerLabelForStatus($history->from_status)
                : $history->from_status?->label(),
            'to_status' => $history->to_status?->value,
            'to_status_label' => $order
                ? $order->customerLabelForStatus($history->to_status)
                : $history->to_status?->label(),
            'created_at' => $history->created_at?->toIso8601String(),
        ];
    }
}
