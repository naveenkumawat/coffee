<?php

namespace App\Http\Resources\Api\V1\Dining;

use App\Http\Resources\Api\V1\OrderResource;
use App\Models\DiningSession;
use App\Services\Dining\DiningServiceRequestServiceInterface;
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
            'payment_status_label' => $session->payment_status?->label(),
            'payment_transaction_id' => $session->payment_reference,
            'payment_proof' => $session->isCashPayment() || $session->payment_method?->isOnline()
                ? null
                : [
                    'uploaded' => $session->hasManualPaymentEvidence(),
                    'transaction_id' => $session->payment_reference,
                    'has_screenshot' => $session->hasPaymentProof(),
                    'uploaded_at' => $session->payment_proof_uploaded_at?->toIso8601String(),
                    'mime' => $session->payment_proof_mime,
                    'size' => $session->payment_proof_size,
                    'rejection_notes' => $session->payment_proof_rejection_notes,
                ],
            'totals' => [
                'subtotal' => $bill['subtotal'],
                'discount' => $bill['discount'],
                'tax' => $bill['tax'],
                'total' => $bill['total'],
                'finalized' => (bool) ($bill['finalized'] ?? false),
                'tax_label' => $session->tax_label_snapshot,
                'tax_percent' => $session->tax_percent_snapshot,
                'tax_enabled' => (bool) $session->tax_enabled_snapshot,
            ],
            'running_bill' => ($bill['finalized'] ?? false) ? null : $bill,
            'final_bill' => ($bill['finalized'] ?? false) ? $bill : null,
            'drafts' => $session->relationLoaded('drafts')
                ? $session->drafts->map(static function ($draft): array {
                    $addOns = $draft->relationLoaded('draftAddOns')
                        ? $draft->draftAddOns
                        : $draft->draftAddOns()->with('addOn')->get();

                    $addonLine = '0.00';
                    $addOnPayload = [];
                    foreach ($addOns as $draftAddOn) {
                        $unit = bcdiv((string) $draftAddOn->unit_price, '1', 2);
                        $line = bcmul($unit, (string) ((int) $draftAddOn->quantity * (int) $draft->quantity), 2);
                        $addonLine = bcadd($addonLine, $line, 2);
                        $addOnPayload[] = [
                            'add_on_id' => (int) $draftAddOn->add_on_id,
                            'name' => $draftAddOn->addOn?->name,
                            'quantity' => (int) $draftAddOn->quantity,
                            'unit_price' => $unit,
                        ];
                    }

                    $baseUnit = $draft->productVariant?->price;
                    $baseLine = $baseUnit !== null
                        ? bcmul((string) $baseUnit, (string) $draft->quantity, 2)
                        : null;
                    $lineTotal = $baseLine !== null ? bcadd($baseLine, $addonLine, 2) : null;

                    return [
                        'id' => $draft->getKey(),
                        'product_variant_id' => $draft->product_variant_id,
                        'quantity' => $draft->quantity,
                        'product_name' => $draft->productVariant?->product?->name,
                        'variant_name' => $draft->productVariant?->name,
                        'unit_price' => $baseUnit,
                        'base_line_total' => $baseLine,
                        'addon_line_total' => $addonLine,
                        'line_total' => $lineTotal,
                        'add_ons' => $addOnPayload,
                    ];
                })->values()->all()
                : [],
            'rounds' => $session->relationLoaded('orders')
                ? OrderResource::collection($session->orders)
                : [],
            'capabilities' => [
                'can_add_rounds' => $session->allowsNewRounds(),
                'can_upload_payment_proof' => $session->canSubmitManualPaymentEvidence(),
                'can_submit_transaction_id' => $session->canSubmitManualPaymentEvidence()
                    && $session->payment_method?->apiKey() === 'manual_upi',
                'can_pay' => in_array($session->status?->value, ['billing_requested', 'awaiting_payment'], true)
                    && $session->payment_status?->value !== 'confirmed',
                'can_call_waiter' => $session->allowsNewRounds()
                    && $session->customer_id !== null
                    && (int) $session->customer_id === (int) $request->user()?->getKey(),
            ],
            'service_request' => $this->currentServiceRequestPayload($session),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function currentServiceRequestPayload(DiningSession $session): ?array
    {
        $open = null;

        if ($session->relationLoaded('serviceRequests')) {
            $open = $session->serviceRequests->first(
                static fn ($row): bool => in_array($row->status?->value, ['pending', 'claimed'], true),
            );
        } else {
            $open = app(DiningServiceRequestServiceInterface::class)
                ->currentForSession($session);
        }

        if ($open === null) {
            return null;
        }

        return (new DiningServiceRequestResource($open))->resolve();
    }
}
