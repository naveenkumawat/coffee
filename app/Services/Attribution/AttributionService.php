<?php

namespace App\Services\Attribution;

use App\Enums\AttributionFunnelStage;
use App\Enums\AttributionMode;
use App\Enums\AttributionSourceType;
use App\Enums\BehaviourEventType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CartItem;
use App\Models\CommerceAttributionEvent;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AttributionService implements AttributionServiceInterface
{
    /**
     * Validate and normalize client-claimed attribution against server evidence.
     *
     * @param  array<string, mixed>|null  $claimed
     * @return array<string, mixed>|null
     */
    public function resolveForCartAdd(
        ?array $claimed,
        int $productId,
        ?User $customer = null,
        ?string $visitorKey = null,
    ): ?array {
        if ($claimed === null || $claimed === []) {
            return null;
        }

        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $visitorKey = filled($visitorKey) ? trim((string) $visitorKey) : null;

        $sourceType = AttributionSourceType::tryFrom((string) ($claimed['source_type'] ?? ''));
        $requestId = trim((string) ($claimed['request_id'] ?? ''));

        if ($sourceType === null || $requestId === '' || strlen($requestId) > 80) {
            throw ValidationException::withMessages([
                'attribution' => 'Attribution claim is invalid.',
            ]);
        }

        if (! preg_match('/^[A-Za-z0-9:_\\-]+$/', $requestId)) {
            throw ValidationException::withMessages([
                'attribution' => 'Attribution request id is invalid.',
            ]);
        }

        $sourceId = isset($claimed['source_id']) ? (int) $claimed['source_id'] : null;

        if ($sourceType === AttributionSourceType::Campaign) {
            if ($sourceId === null || $sourceId <= 0 || ! Campaign::query()->whereKey($sourceId)->exists()) {
                throw ValidationException::withMessages([
                    'attribution.source_id' => 'Campaign attribution requires a valid campaign.',
                ]);
            }
        } else {
            $sourceId = null;
        }

        $direct = $this->findEvidenceEvent(
            sourceType: $sourceType,
            requestId: $requestId,
            productId: $productId,
            sourceId: $sourceId,
            customer: $customer,
            visitorKey: $visitorKey,
            preferClick: true,
        );

        $mode = AttributionMode::Direct;
        $evidence = $direct;

        if ($evidence === null && (bool) config('coffee.behaviour.attribution.view_through_enabled', true)) {
            $evidence = $this->findEvidenceEvent(
                sourceType: $sourceType,
                requestId: $requestId,
                productId: $productId,
                sourceId: $sourceId,
                customer: $customer,
                visitorKey: $visitorKey,
                preferClick: false,
            );
            $mode = AttributionMode::ViewThrough;
        }

        if ($evidence === null) {
            throw ValidationException::withMessages([
                'attribution' => 'No matching recommendation/campaign interaction was found for this attribution.',
            ]);
        }

        $metadata = is_array($evidence->metadata) ? $evidence->metadata : [];

        return [
            'source_type' => $sourceType->value,
            'source_id' => $sourceId,
            'request_id' => $requestId,
            'strategy' => $this->nullableString($claimed['strategy'] ?? $metadata['strategy'] ?? null, 64),
            'reason' => $this->nullableString($claimed['reason'] ?? $metadata['reason'] ?? null, 64),
            'placement' => $this->nullableString($claimed['placement'] ?? $metadata['placement'] ?? null, 64),
            'context' => $this->nullableString($claimed['context'] ?? $metadata['context'] ?? null, 64),
            'mode' => $mode->value,
            'attributed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attribution
     */
    public function stampCartItem(
        CartItem $cartItem,
        array $attribution,
        ?User $customer = null,
        ?string $visitorKey = null,
        int $quantityAdded = 1,
    ): void {
        // First attribution wins when merging quantity onto an existing line.
        if (is_array($cartItem->attribution) && $cartItem->attribution !== []) {
            return;
        }

        $cartItem->forceFill(['attribution' => $attribution])->save();

        $productId = (int) ($cartItem->productVariant?->product_id
            ?? $cartItem->productVariant()->value('product_id')
            ?? 0);

        $this->recordFunnelEvent([
            'source_type' => $attribution['source_type'],
            'source_id' => $attribution['source_id'] ?? null,
            'request_id' => $attribution['request_id'],
            'product_id' => $productId > 0 ? $productId : null,
            'product_variant_id' => (int) $cartItem->product_variant_id,
            'customer_id' => $customer?->getKey(),
            'visitor_key' => $visitorKey,
            'strategy' => $attribution['strategy'] ?? null,
            'reason' => $attribution['reason'] ?? null,
            'placement' => $attribution['placement'] ?? null,
            'context' => $attribution['context'] ?? null,
            'attribution_mode' => $attribution['mode'] ?? AttributionMode::Direct->value,
            'stage' => AttributionFunnelStage::CartAdded->value,
            'units' => max(1, $quantityAdded),
            'revenue_amount' => null,
            'idempotency_key' => sprintf(
                'cart:%s:%d:%s',
                $attribution['request_id'],
                (int) $cartItem->product_variant_id,
                (string) $cartItem->getKey(),
            ),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Snapshot cart attribution onto prepared order line payload.
     *
     * @param  array<string, mixed>|null  $attribution
     * @return array<string, mixed>|null
     */
    public function snapshotForOrderItem(?array $attribution): ?array
    {
        if ($attribution === null || $attribution === []) {
            return null;
        }

        return [
            'source_type' => (string) ($attribution['source_type'] ?? ''),
            'source_id' => isset($attribution['source_id']) ? (int) $attribution['source_id'] : null,
            'request_id' => (string) ($attribution['request_id'] ?? ''),
            'strategy' => $attribution['strategy'] ?? null,
            'reason' => $attribution['reason'] ?? null,
            'placement' => $attribution['placement'] ?? null,
            'context' => $attribution['context'] ?? null,
            'mode' => (string) ($attribution['mode'] ?? AttributionMode::Direct->value),
            'attributed_at' => (string) ($attribution['attributed_at'] ?? now()->toIso8601String()),
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    public function recordConversionsForOrder(Order $order): void
    {
        $order = $order->fresh(['items', 'diningSession']) ?? $order;

        if (! $this->orderIsConversionEligible($order)) {
            return;
        }

        foreach ($order->items as $item) {
            $this->recordConversionForOrderItem($order, $item);
        }
    }

    public function orderIsConversionEligible(Order $order): bool
    {
        $status = $order->status instanceof OrderStatus
            ? $order->status
            : OrderStatus::tryFrom((string) $order->status);

        if ($status !== OrderStatus::Completed) {
            return false;
        }

        if ($order->dining_session_id !== null) {
            $session = $order->diningSession;
            $sessionPayment = $session?->payment_status instanceof PaymentStatus
                ? $session->payment_status
                : PaymentStatus::tryFrom((string) ($session?->payment_status ?? ''));

            return $sessionPayment === PaymentStatus::Confirmed;
        }

        $payment = $order->payment_status instanceof PaymentStatus
            ? $order->payment_status
            : PaymentStatus::tryFrom((string) $order->payment_status);

        return $payment === PaymentStatus::Confirmed;
    }

    protected function recordConversionForOrderItem(Order $order, OrderItem $item): void
    {
        $attribution = is_array($item->attribution) ? $item->attribution : null;

        if ($attribution === null || ($attribution['request_id'] ?? '') === '') {
            return;
        }

        $sourceType = AttributionSourceType::tryFrom((string) ($attribution['source_type'] ?? ''));

        if ($sourceType === null) {
            return;
        }

        $idempotencyKey = sprintf('converted:order_item:%d', (int) $item->getKey());

        $created = $this->recordFunnelEvent([
            'source_type' => $sourceType->value,
            'source_id' => $attribution['source_id'] ?? null,
            'request_id' => (string) $attribution['request_id'],
            'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
            'product_variant_id' => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
            'customer_id' => $order->customer_id !== null ? (int) $order->customer_id : null,
            'visitor_key' => null,
            'strategy' => $attribution['strategy'] ?? null,
            'reason' => $attribution['reason'] ?? null,
            'placement' => $attribution['placement'] ?? null,
            'context' => $attribution['context'] ?? null,
            'attribution_mode' => $attribution['mode'] ?? AttributionMode::Direct->value,
            'stage' => AttributionFunnelStage::Converted->value,
            'order_id' => (int) $order->getKey(),
            'order_item_id' => (int) $item->getKey(),
            'units' => (int) $item->quantity,
            'revenue_amount' => (string) $item->line_subtotal,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $order->completed_at ?? now(),
        ]);

        if ($created === null) {
            return;
        }

        $this->recordServerConversionBehaviour($order, $item, $attribution, $sourceType);
    }

    /**
     * @param  array<string, mixed>  $attribution
     */
    protected function recordServerConversionBehaviour(
        Order $order,
        OrderItem $item,
        array $attribution,
        AttributionSourceType $sourceType,
    ): void {
        if (! (bool) config('coffee.behaviour.enabled', true)) {
            return;
        }

        $eventType = $sourceType === AttributionSourceType::Campaign
            ? BehaviourEventType::CampaignConverted
            : BehaviourEventType::RecommendationConverted;

        $idempotencyKey = sprintf(
            'server:%s:order_item:%d',
            $eventType->value,
            (int) $item->getKey(),
        );

        try {
            $existing = DB::table('customer_behaviour_events')
                ->where('idempotency_key', $idempotencyKey)
                ->exists();

            if ($existing) {
                return;
            }

            DB::table('customer_behaviour_events')->insert([
                'event_type' => $eventType->value,
                'source' => 'server',
                'customer_id' => $order->customer_id,
                'visitor_key' => null,
                'product_id' => $item->product_id,
                'product_category_id' => null,
                'product_variant_id' => $item->product_variant_id,
                'order_id' => $order->getKey(),
                'page_context' => null,
                'metadata' => json_encode([
                    'request_id' => $attribution['request_id'] ?? null,
                    'source_type' => $sourceType->value,
                    'source_id' => $attribution['source_id'] ?? null,
                    'strategy' => $attribution['strategy'] ?? null,
                    'reason' => $attribution['reason'] ?? null,
                    'placement' => $attribution['placement'] ?? null,
                    'mode' => $attribution['mode'] ?? null,
                    'order_item_id' => (int) $item->getKey(),
                    'units' => (int) $item->quantity,
                    'revenue_amount' => (string) $item->line_subtotal,
                ], JSON_THROW_ON_ERROR),
                'occurred_at' => ($order->completed_at ?? now())->toDateTimeString(),
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('attribution.conversion_behaviour_failed', [
                'order_item_id' => $item->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordFunnelEvent(array $payload): ?CommerceAttributionEvent
    {
        $key = (string) $payload['idempotency_key'];

        $existing = CommerceAttributionEvent::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return null;
        }

        try {
            return CommerceAttributionEvent::query()->create($payload);
        } catch (Throwable $exception) {
            $existing = CommerceAttributionEvent::query()->where('idempotency_key', $key)->first();

            if ($existing !== null) {
                return null;
            }

            Log::warning('attribution.funnel_event_failed', [
                'idempotency_key' => $key,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function findEvidenceEvent(
        AttributionSourceType $sourceType,
        string $requestId,
        int $productId,
        ?int $sourceId,
        ?User $customer,
        ?string $visitorKey,
        bool $preferClick,
    ): ?CustomerBehaviourEvent {
        $clickType = $sourceType === AttributionSourceType::Campaign
            ? BehaviourEventType::CampaignClicked
            : BehaviourEventType::RecommendationClicked;
        $impressionType = $sourceType === AttributionSourceType::Campaign
            ? BehaviourEventType::CampaignImpression
            : BehaviourEventType::RecommendationImpression;

        $types = $preferClick
            ? [$clickType->value]
            : [$impressionType->value];

        $windowHours = max(1, (int) config(
            $preferClick
                ? 'coffee.behaviour.attribution.direct_window_hours'
                : 'coffee.behaviour.attribution.view_through_window_hours',
            $preferClick ? 72 : 24,
        ));

        $query = CustomerBehaviourEvent::query()
            ->whereIn('event_type', $types)
            ->where('occurred_at', '>=', now()->subHours($windowHours))
            ->where(function ($q) use ($requestId): void {
                $q->where('metadata->request_id', $requestId);
            })
            ->orderByDesc('occurred_at');

        if ($sourceType === AttributionSourceType::Recommendation || $preferClick) {
            $query->where('product_id', $productId);
        } elseif ($sourceType === AttributionSourceType::Campaign) {
            // Campaign impressions may not carry product_id; clicks to product CTAs should.
            $query->where(function ($q) use ($productId): void {
                $q->where('product_id', $productId)->orWhereNull('product_id');
            });
        }

        if ($sourceId !== null) {
            $query->where('metadata->campaign_id', $sourceId);
        }

        $this->constrainActor($query, $customer, $visitorKey);

        return $query->first();
    }

    /**
     * @param  Builder<CustomerBehaviourEvent>  $query
     */
    protected function constrainActor($query, ?User $customer, ?string $visitorKey): void
    {
        $query->where(function ($q) use ($customer, $visitorKey): void {
            if ($customer !== null) {
                $q->where('customer_id', $customer->getKey());

                if ($visitorKey !== null) {
                    $q->orWhere(function ($inner) use ($visitorKey): void {
                        $inner->where('visitor_key', $visitorKey)->whereNull('customer_id');
                    });
                }

                return;
            }

            if ($visitorKey !== null) {
                $q->where('visitor_key', $visitorKey)->whereNull('customer_id');

                return;
            }

            $q->whereRaw('1 = 0');
        });
    }

    protected function nullableString(mixed $value, int $max): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
