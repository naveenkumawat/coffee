<?php

namespace App\Http\Resources\Api\V1\Dining;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\User;
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
                ->map(fn (Order $order): array => $this->roundPayload($order))
                ->all()
            : [];

        $base['capabilities'] = array_merge($base['capabilities'] ?? [], [
            'can_request_bill' => ($actor?->can('requestBill', $session) ?? false)
                && $session->status === DiningSessionStatus::Open,
            'can_change_payment_method' => $actor?->can('changePaymentMethod', $session) ?? false,
            'can_mark_cash_received' => $actor?->can('markCashReceived', $session) ?? false,
            'can_close' => $actor?->can('close', $session) ?? false,
            'can_reopen' => $actor?->can('reopen', $session) ?? false,
            'can_confirm_upi' => false,
            'can_reject_upi_proof' => false,
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
    protected function roundPayload(Order $order): array
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
            ])
            ->values()
            ->all();

        $activeTickets = $preparations->filter(
            static fn ($ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled,
        );
        $readyToServe = $activeTickets->isNotEmpty()
            && $activeTickets->every(
                static fn ($ticket): bool => $ticket->status === OrderPreparationStatus::Ready,
            );
        $isPreparing = $activeTickets->contains(
            static fn ($ticket): bool => in_array(
                $ticket->status,
                [OrderPreparationStatus::Accepted, OrderPreparationStatus::Preparing],
                true,
            ),
        );

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
            'ready_to_serve' => $readyToServe && ! in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true),
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
}
