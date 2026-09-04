<?php

namespace App\Services\Segment;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Enums\UserRole;
use App\Models\AudienceSegment;
use App\Models\User;
use App\Services\Favourite\FavouriteServiceInterface;
use App\Services\Loyalty\LoyaltyPersonalisationContextServiceInterface;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use App\Services\Targeting\TargetingRuleEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SegmentService implements SegmentServiceInterface
{
    public function __construct(
        protected PersonalisationProfileServiceInterface $profiles,
        protected FavouriteServiceInterface $favourites,
        protected TargetingRuleEvaluator $evaluator,
        protected LoyaltyPersonalisationContextServiceInterface $loyaltyContext,
    ) {}

    /**
     * @param  array{
     *     visitor_key?: string|null,
     *     product_id?: int|null,
     *     category_id?: int|null,
     *     cart_product_ids?: list<int>,
     *     fulfilment_method?: string|null,
     *     location_city?: string|null,
     *     location_zone?: string|null,
     *     location_available?: bool|null
     * }  $input
     * @return array{matches: bool, segment_id: int, stable_key: string|null, reason?: string}
     */
    public function matches(AudienceSegment|int $segment, array $input = [], ?User $customer = null): array
    {
        $segment = $segment instanceof AudienceSegment
            ? $segment
            : AudienceSegment::query()->find($segment);

        if ($segment === null) {
            return [
                'matches' => false,
                'segment_id' => 0,
                'stable_key' => null,
                'reason' => 'missing',
            ];
        }

        if (! $segment->isActive()) {
            return [
                'matches' => false,
                'segment_id' => (int) $segment->id,
                'stable_key' => $segment->stable_key,
                'reason' => 'inactive',
            ];
        }

        $context = $this->buildContext($input, $customer);

        if (! $this->actorScopeAllows($segment, $context['customer'] ?? null)) {
            return [
                'matches' => false,
                'segment_id' => (int) $segment->id,
                'stable_key' => $segment->stable_key,
                'reason' => 'actor_scope',
            ];
        }

        $matched = $this->evaluator->matchesGroups(
            is_array($segment->rules) ? $segment->rules : [],
            $context,
        );

        return [
            'matches' => $matched,
            'segment_id' => (int) $segment->id,
            'stable_key' => $segment->stable_key,
            'reason' => $matched ? 'matched' : 'rules',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{id: int, name: string, stable_key: string|null}>
     */
    public function matchingSegments(array $input = [], ?User $customer = null): array
    {
        $context = $this->buildContext($input, $customer);
        $matches = [];

        foreach ($this->activeSegments() as $segment) {
            if (! $this->actorScopeAllows($segment, $context['customer'] ?? null)) {
                continue;
            }

            if ($this->evaluator->matchesGroups(is_array($segment->rules) ? $segment->rules : [], $context)) {
                $matches[] = [
                    'id' => (int) $segment->id,
                    'name' => $segment->name,
                    'stable_key' => $segment->stable_key,
                ];
            }
        }

        return $matches;
    }

    /**
     * Identity-safe short TTL match cache for campaign eligibility hot paths.
     */
    public function matchesCached(int $segmentId, array $input = [], ?User $customer = null): bool
    {
        $ttl = max(15, (int) config('coffee.behaviour.segments.cache_ttl_seconds', 60));
        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $visitorKey = isset($input['visitor_key']) ? trim((string) $input['visitor_key']) : '';
        $actorKey = $customer !== null ? 'c:'.$customer->getKey() : 'v:'.($visitorKey !== '' ? $visitorKey : 'anon');
        $version = (int) Cache::get('audience_segments.match_version', 1);
        $cacheKey = 'segment.match.v1.'.$version.'.'.$segmentId.'.'.$actorKey;

        return (bool) Cache::remember($cacheKey, $ttl, function () use ($segmentId, $input, $customer): bool {
            return $this->matches($segmentId, $input, $customer)['matches'];
        });
    }

    public function flushMatchCache(): void
    {
        // Definition cache + bump match-cache version (bounded TTL entries become unreachable).
        Cache::forget('audience_segments.active.v1');
        Cache::forever('audience_segments.match_version', ((int) Cache::get('audience_segments.match_version', 1)) + 1);
    }

    /**
     * Bounded approximate count for Admin preview (capped scan).
     *
     * @return array{scanned: int, matched: int, capped: bool}
     */
    public function approximateCustomerMatchCount(AudienceSegment $segment, int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $customers = User::query()
            ->where('role', UserRole::Customer->value)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $matched = 0;

        foreach ($customers as $customer) {
            if ($this->matches($segment, [], $customer)['matches']) {
                $matched++;
            }
        }

        return [
            'scanned' => $customers->count(),
            'matched' => $matched,
            'capped' => $customers->count() >= $limit,
        ];
    }

    /**
     * @return Collection<int, AudienceSegment>
     */
    protected function activeSegments(): Collection
    {
        $ttl = max(30, (int) config('coffee.behaviour.segments.definition_cache_ttl_seconds', 120));

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember('audience_segments.active.v1', $ttl, function (): array {
            return AudienceSegment::query()
                ->where('status', AudienceSegmentStatus::Active->value)
                ->orderBy('name')
                ->get()
                ->map(fn (AudienceSegment $segment): array => $segment->toArray())
                ->all();
        });

        return collect($rows)->map(function (array $row): AudienceSegment {
            $segment = new AudienceSegment;
            $segment->forceFill($row);
            $segment->exists = true;

            return $segment;
        });
    }

    protected function actorScopeAllows(AudienceSegment $segment, ?User $customer): bool
    {
        $scope = $segment->actor_scope instanceof AudienceSegmentActor
            ? $segment->actor_scope
            : AudienceSegmentActor::tryFrom((string) $segment->actor_scope);

        return match ($scope) {
            AudienceSegmentActor::Both => true,
            AudienceSegmentActor::Customer => $customer !== null,
            AudienceSegmentActor::Visitor => $customer === null,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildContext(array $input = [], ?User $customer = null): array
    {
        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $visitorKey = isset($input['visitor_key']) ? trim((string) $input['visitor_key']) : null;
        $visitorKey = $visitorKey !== '' ? $visitorKey : null;

        $profile = null;

        if ($customer !== null) {
            $profile = $this->profiles->profilePayloadForCustomer((int) $customer->getKey());
        } elseif ($visitorKey !== null) {
            $profile = $this->profiles->profilePayloadForVisitor($visitorKey);
        }

        $favouriteIds = $customer !== null
            ? $this->favourites->productIdsForCustomer($customer)->map(fn ($id): int => (int) $id)->all()
            : [];

        $cartProductIds = array_values(array_unique(array_filter(array_map('intval', $input['cart_product_ids'] ?? []))));

        return [
            'customer' => $customer,
            'visitor_key' => $visitorKey,
            'product_id' => isset($input['product_id']) ? (int) $input['product_id'] : null,
            'category_id' => isset($input['category_id']) ? (int) $input['category_id'] : null,
            'cart_product_ids' => $cartProductIds,
            'cart_category_ids' => $this->evaluator->cartCategoryIds($cartProductIds),
            'fulfilment_method' => isset($input['fulfilment_method']) ? trim((string) $input['fulfilment_method']) : null,
            'location_city' => isset($input['location_city']) ? trim((string) $input['location_city']) : null,
            'location_zone' => isset($input['location_zone']) ? trim((string) $input['location_zone']) : null,
            'location_available' => (bool) ($input['location_available'] ?? false),
            'profile' => $profile,
            'completed_order_count' => $this->evaluator->completedOrderCount($customer),
            'favourite_ids' => $favouriteIds,
            'purchased_product_ids' => $this->evaluator->purchasedProductIds($customer),
            'purchased_category_ids' => $this->evaluator->purchasedCategoryIds($customer),
            'last_purchase_days' => $this->evaluator->lastPurchaseDays($customer),
            'is_returning_visitor' => $this->evaluator->isReturningVisitor($visitorKey, $customer),
            'loyalty' => $this->loyaltyContext->forActor($customer),
        ];
    }
}
