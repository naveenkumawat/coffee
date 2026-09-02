<?php

namespace App\Http\Resources\Api\V1\Dining;

use App\Http\Resources\Api\V1\OrderResource;
use App\Models\DiningSession;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DiningSession $session */
        $session = $this->resource;
        $bill = app(DiningSessionServiceInterface::class)->displayBill($session);

        return [
            'id' => $session->getKey(),
            'session_number' => $session->session_number,
            'status' => $session->status?->value,
            'status_label' => $session->status?->label(),
            'guest_count' => $session->guest_count,
            'table' => [
                'id' => $session->cafe_table_id,
                'label' => $session->tableDisplayLabel(),
            ],
            'customer' => [
                'id' => $session->customer_id,
                'name' => $session->customer_name_snapshot,
                'phone' => $session->customer_phone_snapshot,
            ],
            'opened_at' => $session->opened_at?->toIso8601String(),
            'billing_requested_at' => $session->billing_requested_at?->toIso8601String(),
            'bill_generated_at' => $session->bill_generated_at?->toIso8601String(),
            'paid_at' => $session->paid_at?->toIso8601String(),
            'closed_at' => $session->closed_at?->toIso8601String(),
            'payment_method' => $session->payment_method?->apiKey(),
            'payment_status' => $session->payment_status?->value,
            'totals' => [
                'subtotal' => $bill['subtotal'],
                'discount' => $bill['discount'],
                'tax' => $bill['tax'],
                'total' => $bill['total'],
                'finalized' => (bool) ($bill['finalized'] ?? false),
            ],
            'running_bill' => ($bill['finalized'] ?? false) ? null : $bill,
            'final_bill' => ($bill['finalized'] ?? false) ? $bill : null,
            'drafts' => $session->relationLoaded('drafts')
                ? $session->drafts->map(static fn ($draft): array => [
                    'id' => $draft->getKey(),
                    'product_variant_id' => $draft->product_variant_id,
                    'quantity' => $draft->quantity,
                    'product_name' => $draft->productVariant?->product?->name,
                    'variant_name' => $draft->productVariant?->name,
                    'unit_price' => $draft->productVariant?->price,
                ])->values()->all()
                : [],
            'rounds' => $session->relationLoaded('orders')
                ? OrderResource::collection($session->orders)
                : [],
            'capabilities' => [
                'can_add_rounds' => $session->allowsNewRounds(),
                'can_upload_payment_proof' => $session->canUploadPaymentProof(),
            ],
        ];
    }
}
