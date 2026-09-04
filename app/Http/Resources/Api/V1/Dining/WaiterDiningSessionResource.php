<?php

namespace App\Http\Resources\Api\V1\Dining;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\User;
use App\Services\Dining\DiningRoundCancellationPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaiterDiningSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DiningSession $session */
        $session = $this->resource;
        $base = (new DiningSessionResource($session))->toArray($request);
        /** @var User|null $actor */
        $actor = $request->user();

        $base['rounds'] = $session->relationLoaded('orders')
            ? $session->orders
                ->sortBy('dining_round_number')
                ->values()
                ->map(fn (Order $order): array => $this->roundPayload($order, $session, $actor))
                ->all()
            : [];

        $activeRoundCount = $session->relationLoaded('orders')
            ? $session->orders
                ->reject(static fn (Order $order): bool => in_array(
                    $order->status,
                    [OrderStatus::Cancelled, OrderStatus::Rejected],
                    true,
                ))
                ->count()
            : $session->orders()
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
                ->count();

        $billReadyStatuses = [
            DiningSessionStatus::BillingRequested,
            DiningSessionStatus::AwaitingPayment,
        ];
        $billReady = in_array($session->status, $billReadyStatuses, true);
        $paymentConfirmed = $session->status === DiningSessionStatus::Paid
            || $session->payment_status === PaymentStatus::Confirmed;
        $canReopenStatus = in_array($session->status, $billReadyStatuses, true) && ! $paymentConfirmed;

        $base['capabilities'] = array_merge($base['capabilities'] ?? [], [
            'can_request_bill' => ($actor?->can('requestBill', $session) ?? false)
                && $session->status === DiningSessionStatus::Open
                && $activeRoundCount > 0,
            'can_change_payment_method' => ($actor?->can('changePaymentMethod', $session) ?? false)
                && $billReady
                && ! $paymentConfirmed,
            'can_mark_cash_received' => ($actor?->can('markCashReceived', $session) ?? false)
                && $billReady
                && ! $paymentConfirmed,
            'can_close' => ($actor?->can('close', $session) ?? false) && $paymentConfirmed,
            'can_reopen' => ($actor?->can('reopen', $session) ?? false) && $canReopenStatus,
            'can_confirm_upi' => false,
            'can_reject_upi_proof' => false,
            'awaiting_operator_upi' => $billReady
                && ! $paymentConfirmed
                && $session->payment_method?->value === 'manual'
                && $session->payment_status === PaymentStatus::AwaitingReview,
            'close_blocked_reason' => $this->closeBlockedReason($session, $actor),
            'draft_item_count' => $session->relationLoaded('drafts')
                ? (int) $session->drafts->sum('quantity')
                : (int) $session->drafts()->sum('quantity'),
            'has_unsent_draft' => $session->relationLoaded('drafts')
                ? $session->drafts->isNotEmpty()
                : $session->drafts()->exists(),
        ]);

        $base['ready_to_serve_round_ids'] = collect($base['rounds'])
            ->filter(static fn (array $round): bool => (bool) ($round['ready_to_serve'] ?? false))
            ->pluck('id')
            ->values()
            ->all();

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    protected function roundPayload(Order $order, DiningSession $session, ?User $actor): array
    {
        $preparations = $order->relationLoaded('preparations')
            ? $order->preparations
            : $order->preparations()->get();

        $stations = $preparations
            ->filter(static fn ($ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled)
            ->map(static fn ($ticket): array => [
                'station' => $ticket->station?->value,
                'station_label' => $ticket->station?->label(),
                'status' => $ticket->status?->value,
                'status_label' => $ticket->status?->label(),
                'ready_at' => $ticket->ready_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $activeTickets = $preparations->filter(
            static fn ($ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled,
        );
        $stationsReady = $activeTickets->isNotEmpty()
            && $activeTickets->every(
                static fn ($ticket): bool => $ticket->status === OrderPreparationStatus::Ready,
            );
        $readyToServe = $stationsReady
            && $order->served_at === null
            && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true);
        $isPreparing = $activeTickets->contains(
            static fn ($ticket): bool => in_array(
                $ticket->status,
                [OrderPreparationStatus::Accepted, OrderPreparationStatus::Preparing],
                true,
            ),
        );

        $overallReadyAt = $readyToServe
            ? $activeTickets
                ->map(static fn ($ticket) => $ticket->ready_at)
                ->filter()
                ->max()
            : null;
        $readyToServeAgeSeconds = $overallReadyAt !== null
            ? max(0, $overallReadyAt->diffInSeconds(now()))
            : null;

        $cancellation = app(DiningRoundCancellationPolicy::class)->evaluate($session, $order, $actor);

        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->with('addOns')->get();

        return [
            'id' => $order->getKey(),
            'order_number' => $order->order_number,
            'round_number' => (int) $order->dining_round_number,
            'status' => $order->status?->value,
            'status_label' => $order->status?->label(),
            'placed_at' => $order->placed_at?->toIso8601String() ?? $order->created_at?->toIso8601String(),
            'subtotal' => $order->subtotal,
            'total_amount' => $order->total_amount,
            'ready_to_serve' => $readyToServe,
            'ready_to_serve_age_seconds' => $readyToServeAgeSeconds,
            'served' => $order->served_at !== null,
            'served_at' => $order->served_at?->toIso8601String(),
            'can_mark_served' => ($actor?->can('markServed', $session) ?? false) && $readyToServe,
            'can_accept' => ($actor?->can('transition', $order) ?? false)
                && $order->status === OrderStatus::Pending,
            'can_cancel' => $cancellation['can_cancel'],
            'cancel_requires_reason' => $cancellation['cancel_requires_reason'],
            'can_void' => $cancellation['can_void'],
            'cancellation_blocked_reason' => $cancellation['cancellation_blocked_reason'],
            'is_preparing' => $isPreparing,
            'stations' => $stations,
            'items' => $items->map(static function ($item): array {
                return [
                    'id' => $item->getKey(),
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_subtotal' => $item->line_subtotal,
                    'preparation_station' => $item->preparation_station?->value,
                    'add_ons' => ($item->relationLoaded('addOns') ? $item->addOns : $item->addOns()->get())
                        ->map(static fn ($addOn): array => [
                            'name' => $addOn->name,
                            'quantity' => (int) $addOn->quantity,
                            'unit_price' => $addOn->unit_price,
                            'total_price' => $addOn->total_price,
                        ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    protected function closeBlockedReason(DiningSession $session, ?User $actor): ?string
    {
        if ($actor?->can('close', $session) !== true) {
            return 'You are not allowed to close this dining session.';
        }

        if ($session->status === DiningSessionStatus::Closed) {
            return 'This session is already closed.';
        }

        if ($session->status === DiningSessionStatus::Paid || $session->payment_status === PaymentStatus::Confirmed) {
            return null;
        }

        return 'Close the session only after payment is confirmed.';
    }
}
