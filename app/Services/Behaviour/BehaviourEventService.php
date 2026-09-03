<?php

namespace App\Services\Behaviour;

use App\Enums\BehaviourEventSource;
use App\Enums\BehaviourEventType;
use App\Models\CustomerBehaviourEvent;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Behaviour\BehaviourEventRepositoryInterface;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class BehaviourEventService implements BehaviourEventServiceInterface
{
    public function __construct(
        protected BehaviourEventRepositoryInterface $events,
        protected PersonalisationProfileServiceInterface $profiles,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('coffee.behaviour.enabled', true);
    }

    /**
     * @param  array{
     *     event_type: string,
     *     visitor_key: string,
     *     product_id?: int|null,
     *     product_category_id?: int|null,
     *     product_variant_id?: int|null,
     *     page_context?: string|null,
     *     metadata?: array<string, mixed>|null,
     *     occurred_at?: string|null,
     *     idempotency_key?: string|null
     * }  $payload
     * @return array{accepted: bool, reason?: string, event_id?: int|null}
     */
    public function ingestClientEvent(array $payload, ?User $customer = null): array
    {
        if (! $this->isEnabled()) {
            return ['accepted' => false, 'reason' => 'disabled'];
        }

        $type = BehaviourEventType::tryFrom((string) $payload['event_type']);

        if ($type === null || ! $type->isClientIngestible()) {
            throw ValidationException::withMessages([
                'event_type' => 'The selected event type is not supported for client ingestion.',
            ]);
        }

        $visitorKey = $this->normalizeVisitorKey((string) $payload['visitor_key']);
        $this->assertVisitorNotOwnedByAnotherCustomer($visitorKey, $customer);

        $references = $this->validateAndNormalizeReferences($type, $payload);
        $metadata = $this->sanitizeMetadata($type, $payload['metadata'] ?? null);
        $pageContext = $this->normalizePageContext($payload['page_context'] ?? null);
        $occurredAt = $this->normalizeOccurredAt($payload['occurred_at'] ?? null);
        $idempotencyKey = $this->normalizeClientIdempotencyKey($payload['idempotency_key'] ?? null);

        if ($idempotencyKey !== null) {
            $existing = $this->events->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return [
                    'accepted' => true,
                    'reason' => 'duplicate',
                    'event_id' => (int) $existing->getKey(),
                ];
            }
        }

        try {
            $event = $this->events->create([
                'event_type' => $type->value,
                'source' => BehaviourEventSource::Client->value,
                'customer_id' => $customer?->getKey(),
                'visitor_key' => $visitorKey,
                'product_id' => $references['product_id'],
                'product_category_id' => $references['product_category_id'],
                'product_variant_id' => $references['product_variant_id'],
                'order_id' => null,
                'page_context' => $pageContext,
                'metadata' => $metadata,
                'occurred_at' => $occurredAt,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (Throwable $exception) {
            if ($idempotencyKey !== null) {
                $existing = $this->events->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    return [
                        'accepted' => true,
                        'reason' => 'duplicate',
                        'event_id' => (int) $existing->getKey(),
                    ];
                }
            }

            throw $exception;
        }

        if ($customer !== null) {
            $this->profiles->dispatchRebuildForCustomer((int) $customer->getKey());
        } else {
            $this->profiles->dispatchRebuildForVisitor($visitorKey);
        }

        return [
            'accepted' => true,
            'event_id' => (int) $event->getKey(),
        ];
    }

    /**
     * @return array{merged: bool, attached: int, reason?: string}
     */
    public function mergeVisitorToCustomer(string $visitorKey, User $customer): array
    {
        if (! $this->isEnabled()) {
            return ['merged' => false, 'attached' => 0, 'reason' => 'disabled'];
        }

        $visitorKey = $this->normalizeVisitorKey($visitorKey);

        $result = DB::transaction(function () use ($visitorKey, $customer): array {
            $identity = $this->events->findVisitorIdentity($visitorKey);

            if ($identity !== null && (int) $identity->customer_id !== (int) $customer->getKey()) {
                return [
                    'merged' => false,
                    'attached' => 0,
                    'reason' => 'visitor_claimed',
                ];
            }

            if ($identity === null) {
                $this->events->claimVisitorIdentity($visitorKey, $customer);
            }

            $attached = $this->events->attachUnclaimedVisitorEvents($visitorKey, $customer);

            return [
                'merged' => true,
                'attached' => $attached,
            ];
        });

        if (($result['merged'] ?? false) === true) {
            $this->profiles->afterVisitorMerged($visitorKey, $customer);
        }

        return $result;
    }

    public function recordOrderCompleted(Order $order): ?CustomerBehaviourEvent
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $orderId = (int) $order->getKey();
        $idempotencyKey = 'server:order_completed:'.$orderId;

        $existing = $this->events->findByIdempotencyKey($idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return $this->events->create([
                'event_type' => BehaviourEventType::OrderCompleted->value,
                'source' => BehaviourEventSource::Server->value,
                'customer_id' => $order->customer_id,
                'visitor_key' => null,
                'product_id' => null,
                'product_category_id' => null,
                'product_variant_id' => null,
                'order_id' => $orderId,
                'page_context' => null,
                'metadata' => [
                    'fulfilment_method' => $order->fulfilment_method?->value ?? $order->fulfilment_method,
                    'total_amount' => (string) ($order->total_amount ?? ''),
                ],
                'occurred_at' => $order->completed_at ?? now(),
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (Throwable $exception) {
            $existing = $this->events->findByIdempotencyKey($idempotencyKey);

            if ($existing !== null) {
                return $existing;
            }

            Log::warning('behaviour.order_completed_failed', [
                'order_id' => $orderId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function pruneExpired(): int
    {
        $days = max(1, (int) config('coffee.behaviour.retention_days', 180));

        return $this->events->pruneOlderThan(now()->subDays($days));
    }

    public function diagnosticsSummary(): array
    {
        return $this->events->diagnosticsSummary();
    }

    protected function normalizeVisitorKey(string $visitorKey): string
    {
        $visitorKey = trim($visitorKey);

        if ($visitorKey === '' || strlen($visitorKey) > 64 || ! preg_match('/^[A-Za-z0-9_-]+$/', $visitorKey)) {
            throw ValidationException::withMessages([
                'visitor_key' => 'The visitor key must be an opaque identifier up to 64 characters.',
            ]);
        }

        return $visitorKey;
    }

    protected function assertVisitorNotOwnedByAnotherCustomer(string $visitorKey, ?User $customer): void
    {
        $identity = $this->events->findVisitorIdentity($visitorKey);

        if ($identity === null) {
            return;
        }

        if ($customer === null || (int) $identity->customer_id !== (int) $customer->getKey()) {
            // Guests may still emit with a claimed visitor key (shared device after claim);
            // authenticated events for a different customer must rotate client-side.
            if ($customer !== null) {
                throw ValidationException::withMessages([
                    'visitor_key' => 'This visitor key is already associated with another customer.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{product_id: ?int, product_category_id: ?int, product_variant_id: ?int}
     */
    protected function validateAndNormalizeReferences(BehaviourEventType $type, array $payload): array
    {
        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : null;
        $categoryId = isset($payload['product_category_id']) ? (int) $payload['product_category_id'] : null;
        $variantId = isset($payload['product_variant_id']) ? (int) $payload['product_variant_id'] : null;

        if ($productId !== null && $productId > 0 && ! $this->events->productExists($productId)) {
            throw ValidationException::withMessages([
                'product_id' => 'The selected product is invalid.',
            ]);
        }

        if ($categoryId !== null && $categoryId > 0 && ! $this->events->categoryExists($categoryId)) {
            throw ValidationException::withMessages([
                'product_category_id' => 'The selected category is invalid.',
            ]);
        }

        if ($variantId !== null && $variantId > 0 && ! $this->events->variantExists($variantId)) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected variant is invalid.',
            ]);
        }

        $requiresProduct = in_array($type, [
            BehaviourEventType::ProductViewed,
            BehaviourEventType::ProductCustomized,
            BehaviourEventType::CartItemAdded,
            BehaviourEventType::CartItemRemoved,
            BehaviourEventType::FavouriteAdded,
            BehaviourEventType::FavouriteRemoved,
            BehaviourEventType::RecommendationImpression,
            BehaviourEventType::RecommendationClicked,
        ], true);

        if ($requiresProduct && ($productId === null || $productId <= 0)) {
            throw ValidationException::withMessages([
                'product_id' => 'A product is required for this event.',
            ]);
        }

        if ($type === BehaviourEventType::CategoryViewed && ($categoryId === null || $categoryId <= 0)) {
            throw ValidationException::withMessages([
                'product_category_id' => 'A category is required for this event.',
            ]);
        }

        return [
            'product_id' => $productId !== null && $productId > 0 ? $productId : null,
            'product_category_id' => $categoryId !== null && $categoryId > 0 ? $categoryId : null,
            'product_variant_id' => $variantId !== null && $variantId > 0 ? $variantId : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    protected function sanitizeMetadata(BehaviourEventType $type, ?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        $maxBytes = max(64, (int) config('coffee.behaviour.metadata_max_bytes', 2048));
        $encoded = json_encode($metadata);

        if ($encoded === false || strlen($encoded) > $maxBytes) {
            throw ValidationException::withMessages([
                'metadata' => 'Metadata exceeds the allowed size.',
            ]);
        }

        $allowed = match ($type) {
            BehaviourEventType::SearchPerformed => ['query', 'result_count'],
            BehaviourEventType::ProductCustomized => ['variant_id', 'addon_ids', 'addon_count'],
            BehaviourEventType::CartItemAdded, BehaviourEventType::CartItemRemoved => ['quantity', 'variant_id', 'addon_count'],
            BehaviourEventType::CheckoutStarted => ['item_count', 'fulfilment_method'],
            BehaviourEventType::ProductViewed, BehaviourEventType::CategoryViewed => ['source'],
            BehaviourEventType::FavouriteAdded, BehaviourEventType::FavouriteRemoved => [],
            BehaviourEventType::RecommendationImpression, BehaviourEventType::RecommendationClicked => [
                'request_id',
                'reason',
                'strategy',
                'placement',
                'context',
            ],
            default => [],
        };

        $clean = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            if ($key === 'query' && is_string($value)) {
                $clean[$key] = $this->normalizeSearchQuery($value);

                continue;
            }

            if (in_array($key, ['result_count', 'quantity', 'addon_count', 'item_count', 'variant_id'], true)) {
                if (is_numeric($value)) {
                    $clean[$key] = (int) $value;
                }

                continue;
            }

            if ($key === 'addon_ids' && is_array($value)) {
                $clean[$key] = array_values(array_unique(array_map(
                    static fn ($id): int => (int) $id,
                    array_slice($value, 0, 20),
                )));

                continue;
            }

            if ($key === 'source' && is_string($value)) {
                $clean[$key] = Str::limit(trim($value), 40, '');

                continue;
            }

            if ($key === 'fulfilment_method' && is_string($value)) {
                $clean[$key] = Str::limit(trim($value), 40, '');

                continue;
            }

            if (in_array($key, ['request_id', 'reason', 'strategy', 'placement', 'context'], true) && is_string($value)) {
                $clean[$key] = Str::limit(trim($value), 80, '');
            }
        }

        if (in_array($type, [BehaviourEventType::RecommendationImpression, BehaviourEventType::RecommendationClicked], true)) {
            if (empty($clean['request_id']) || empty($clean['reason']) || empty($clean['placement'])) {
                throw ValidationException::withMessages([
                    'metadata' => 'Recommendation events require request_id, reason, and placement.',
                ]);
            }
        }

        if ($type === BehaviourEventType::SearchPerformed && empty($clean['query'])) {
            throw ValidationException::withMessages([
                'metadata.query' => 'A normalized search query is required.',
            ]);
        }

        return $clean === [] ? null : $clean;
    }

    protected function normalizeSearchQuery(string $query): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? ''));
        $maxLength = max(1, (int) config('coffee.behaviour.search_query_max_length', 100));

        return Str::limit($normalized, $maxLength, '');
    }

    protected function normalizePageContext(mixed $pageContext): ?string
    {
        if (! is_string($pageContext) || trim($pageContext) === '') {
            return null;
        }

        return Str::limit(trim($pageContext), 160, '');
    }

    protected function normalizeOccurredAt(mixed $occurredAt): Carbon
    {
        if ($occurredAt === null || $occurredAt === '') {
            return now();
        }

        try {
            $parsed = Carbon::parse((string) $occurredAt);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'occurred_at' => 'The occurred at timestamp is invalid.',
            ]);
        }

        if ($parsed->gt(now()->addMinutes(5)) || $parsed->lt(now()->subDays(7))) {
            return now();
        }

        return $parsed;
    }

    protected function normalizeClientIdempotencyKey(mixed $key): ?string
    {
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        $key = trim($key);

        if (strlen($key) > 120 || ! preg_match('/^[A-Za-z0-9:._-]+$/', $key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key format is invalid.',
            ]);
        }

        return 'client:'.$key;
    }
}
