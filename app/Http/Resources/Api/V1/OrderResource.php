<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\OrderFulfilmentMethod;
use App\Models\Order;
use App\Services\Invoice\OrderInvoiceServiceInterface;
use App\Services\Loyalty\LoyaltyServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
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
            'dining_round_number' => $order->isDiningRound() ? $order->dining_round_number : null,
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
            'loyalty_discount_amount' => $order->loyalty_discount_amount ?? '0.00',
            'loyalty_reward' => ($order->loyalty_reward_id !== null || bccomp((string) ($order->loyalty_discount_amount ?? '0'), '0', 2) > 0)
                ? [
                    'id' => $order->loyalty_reward_id,
                    'name' => $order->loyalty_reward_name_snapshot,
                    'reward_type' => $order->loyalty_reward_type_snapshot,
                    'points_cost' => $order->loyalty_reward_points_cost_snapshot,
                    'discount_amount' => $order->loyalty_discount_amount,
                    'description' => $order->loyalty_reward_description_snapshot,
                    'benefit_label' => is_array($order->loyalty_reward_snapshot)
                        ? ($order->loyalty_reward_snapshot['benefit_label'] ?? null)
                        : null,
                ]
                : null,
            'loyalty_feedback' => app(LoyaltyServiceInterface::class)->orderFeedback($order),
            'promotions' => $order->relationLoaded('promotions')
                ? $order->promotions->map(static fn ($promotion): array => [
                    'name' => $promotion->name_snapshot,
                    'code' => $promotion->code_snapshot,
                    'discount_type' => $promotion->discount_type_snapshot?->value ?? $promotion->discount_type_snapshot,
                    'discount_value' => $promotion->discount_value_snapshot,
                    'amount' => $promotion->discount_amount,
                ])->values()->all()
                : [],
            'reward_redemptions' => $order->relationLoaded('rewardRedemptions')
                ? $order->rewardRedemptions->map(static fn ($redemption): array => [
                    'reward_type' => $redemption->reward_type?->value,
                    'description' => $redemption->description_snapshot,
                    'benefit_amount' => $redemption->benefit_amount,
                    'original_amount' => $redemption->original_amount,
                    'preserves_gst_basis' => $redemption->reward_type?->value === 'free_drink',
                    'coupon_code' => $redemption->coupon_code_snapshot,
                ])->values()->all()
                : [],
            'total_amount' => $order->total_amount,
            'tax' => app(TaxCalculatorInterface::class)
                ->fromOrderSnapshot($order)
                ->toCustomerArray(),
            'payment_method' => $order->payment_method?->apiKey() ?? 'manual_upi',
            'payment_method_label' => $order->payment_method?->customerLabel(
                $order->fulfilment_method instanceof OrderFulfilmentMethod
                    ? $order->fulfilment_method
                    : null,
            ),
            'payment_status' => $order->payment_status?->value,
            'payment_status_label' => $order->payment_status?->label(),
            'payment_reference' => $order->payment_reference,
            'payment_transaction_id' => $order->isCashPayment() || $order->payment_method?->isOnline()
                ? null
                : $order->payment_transaction_id,
            'payment_proof' => $order->isCashPayment() || $order->payment_method?->isOnline()
                ? [
                    'uploaded' => false,
                    'uploaded_at' => null,
                    'mime' => null,
                    'size' => null,
                    'can_upload' => false,
                    'can_submit_transaction' => false,
                    'transaction_id' => null,
                    'rejection_notes' => null,
                    'has_screenshot' => false,
                ]
                : [
                    'uploaded' => $order->hasManualPaymentEvidence(),
                    'uploaded_at' => $order->payment_proof_uploaded_at?->toIso8601String(),
                    'mime' => $order->payment_proof_mime,
                    'size' => $order->payment_proof_size,
                    'can_upload' => $order->canSubmitManualPaymentEvidence(),
                    'can_submit_transaction' => $order->canSubmitManualPaymentEvidence(),
                    'transaction_id' => $order->payment_transaction_id,
                    'rejection_notes' => $order->payment_proof_rejection_notes,
                    'has_screenshot' => $order->hasPaymentProof(),
                ],
            'payment_confirmed_at' => $order->payment_confirmed_at?->toIso8601String(),
            'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
            'cash_received_at' => $order->isCashPayment()
                ? $order->payment_confirmed_at?->toIso8601String()
                : null,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'accepted_at' => $order->accepted_at?->toIso8601String(),
            'preparing_at' => $order->preparing_at?->toIso8601String(),
            'ready_for_pickup_at' => $order->ready_for_pickup_at?->toIso8601String(),
            'served_at' => $order->isDiningRound() ? $order->served_at?->toIso8601String() : null,
            'served' => $order->isDiningRound() ? $order->served_at !== null : false,
            'completed_at' => $order->completed_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'rejected_at' => $order->rejected_at?->toIso8601String(),
            'can_cancel' => $request->user()
                ? $order->canCustomerCancel($request->user())
                : false,
            'items' => OrderItemResource::collection($order->items),
            'status_timeline' => OrderStatusHistoryResource::collection(
                $order->statusHistory->each->setRelation('order', $order),
            ),
            'invoice_available' => app(OrderInvoiceServiceInterface::class)->isAvailable($order),
        ];
    }
}
