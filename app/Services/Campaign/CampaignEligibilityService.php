<?php

namespace App\Services\Campaign;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignImpressionEvent;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\UserRole;
use App\Models\Campaign;
use App\Models\CampaignImpression;
use App\Models\Product;
use App\Models\User;
use App\Services\Favourite\FavouriteServiceInterface;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use App\Services\Segment\SegmentServiceInterface;
use App\Services\Targeting\TargetingRuleEvaluator;
use App\Support\PublicMedia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CampaignEligibilityService implements CampaignEligibilityServiceInterface
{
    public function __construct(
        protected PersonalisationProfileServiceInterface $profiles,
        protected FavouriteServiceInterface $favourites,
        protected CampaignRuleValidator $rules,
        protected TargetingRuleEvaluator $evaluator,
        protected SegmentServiceInterface $segments,
    ) {}

    /**
     * @param  array{
     *     placement: string,
     *     visitor_key?: string|null,
     *     session_key?: string|null,
     *     product_id?: int|null,
     *     category_id?: int|null,
     *     cart_product_ids?: list<int>,
     *     fulfilment_method?: string|null,
     *     location_city?: string|null,
     *     location_zone?: string|null,
     *     location_available?: bool|null,
     *     surface?: string|null
     * }  $input
     * @return array{
     *     request_id: string,
     *     campaign: array<string, mixed>|null
     * }
     */
    public function eligible(array $input, ?User $customer = null): array
    {
        $requestId = (string) Str::uuid();
        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $visitorKey = isset($input['visitor_key']) ? trim((string) $input['visitor_key']) : null;
        $visitorKey = $visitorKey !== '' ? $visitorKey : null;
        $sessionKey = isset($input['session_key']) ? trim((string) $input['session_key']) : null;
        $sessionKey = $sessionKey !== '' ? $sessionKey : null;
        $placement = (string) $input['placement'];
        $surface = (string) ($input['surface'] ?? CampaignSurface::Popup->value);

        $profile = null;

        if ($customer !== null) {
            $profile = $this->profiles->profilePayloadForCustomer((int) $customer->getKey());
        } elseif ($visitorKey !== null) {
            $profile = $this->profiles->profilePayloadForVisitor($visitorKey);
        }

        $context = $this->segments->buildContext([
            'visitor_key' => $visitorKey,
            'product_id' => $input['product_id'] ?? null,
            'category_id' => $input['category_id'] ?? null,
            'cart_product_ids' => $input['cart_product_ids'] ?? [],
            'fulfilment_method' => $input['fulfilment_method'] ?? null,
            'location_city' => $input['location_city'] ?? null,
            'location_zone' => $input['location_zone'] ?? null,
            'location_available' => $input['location_available'] ?? false,
        ], $customer);

        $context['placement'] = $placement;
        $context['session_key'] = $sessionKey;
        // Preserve any profile already loaded for campaign-specific reads.
        if ($profile !== null) {
            $context['profile'] = $profile;
        }

        $candidates = $this->activeCampaigns($surface)
            ->filter(fn (Campaign $campaign): bool => $campaign->isSchedulableNow())
            ->filter(fn (Campaign $campaign): bool => $this->matchesPlacement($campaign, $context))
            ->filter(fn (Campaign $campaign): bool => $this->matchesTargeting($campaign, $context))
            ->filter(fn (Campaign $campaign): bool => $this->matchesFrequency($campaign, $context))
            ->sort(function (Campaign $a, Campaign $b): int {
                $priority = $b->priority <=> $a->priority;

                if ($priority !== 0) {
                    return $priority;
                }

                $specificity = $b->placementSpecificity() <=> $a->placementSpecificity();

                if ($specificity !== 0) {
                    return $specificity;
                }

                $startsA = $a->starts_at?->getTimestamp() ?? 0;
                $startsB = $b->starts_at?->getTimestamp() ?? 0;

                if ($startsA !== $startsB) {
                    return $startsB <=> $startsA;
                }

                return $a->id <=> $b->id;
            })
            ->values();

        /** @var Campaign|null $winner */
        $winner = $candidates->first();

        return [
            'request_id' => $requestId,
            'campaign' => $winner !== null ? $this->toPublicPayload($winner, $requestId) : null,
        ];
    }

    /**
     * @param  array{
     *     campaign_id: int,
     *     event_type: string,
     *     visitor_key?: string|null,
     *     session_key?: string|null,
     *     placement?: string|null,
     *     request_id?: string|null,
     *     cta_type?: string|null
     * }  $input
     * @return array{recorded: bool}
     */
    public function recordInteraction(array $input, ?User $customer = null): array
    {
        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $event = CampaignImpressionEvent::from((string) $input['event_type']);
        $visitorKey = isset($input['visitor_key']) ? trim((string) $input['visitor_key']) : null;
        $visitorKey = $visitorKey !== '' ? $visitorKey : null;

        CampaignImpression::query()->create([
            'campaign_id' => (int) $input['campaign_id'],
            'customer_id' => $customer?->getKey(),
            'visitor_key' => $visitorKey,
            'session_key' => isset($input['session_key']) ? trim((string) $input['session_key']) : null,
            'event_type' => $event,
            'placement' => $input['placement'] ?? null,
            'request_id' => $input['request_id'] ?? null,
            'cta_type' => $input['cta_type'] ?? null,
            'occurred_at' => now(),
        ]);

        return ['recorded' => true];
    }

    public function flushConfigCache(): void
    {
        Cache::forget('campaigns.active.popup.v1');
        Cache::forget('campaigns.active.banner.v1');
        Cache::forget('campaigns.active.inline.v1');
        Cache::forget('campaigns.active.landing.v1');
    }

    /**
     * @return Collection<int, Campaign>
     */
    protected function activeCampaigns(string $surface): Collection
    {
        $ttl = max(30, (int) config('coffee.behaviour.campaigns.cache_ttl_seconds', 120));
        $key = 'campaigns.active.'.$surface.'.v1';

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember($key, $ttl, function () use ($surface): array {
            return Campaign::query()
                ->where('status', CampaignStatus::Active->value)
                ->where('surface', $surface)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get()
                ->map(fn (Campaign $campaign): array => $campaign->toArray())
                ->all();
        });

        return collect($rows)->map(function (array $row): Campaign {
            $campaign = new Campaign;
            $campaign->forceFill($row);
            $campaign->exists = true;

            return $campaign;
        });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function matchesPlacement(Campaign $campaign, array $context): bool
    {
        $rules = is_array($campaign->placement_rules) ? $campaign->placement_rules : [];
        $placements = $rules['placements'] ?? [];

        if (! is_array($placements) || $placements === []) {
            return false;
        }

        $placement = (string) $context['placement'];

        if (! in_array('global', $placements, true) && ! in_array($placement, $placements, true)) {
            return false;
        }

        $productIds = array_map('intval', $rules['product_ids'] ?? []);
        $categoryIds = array_map('intval', $rules['category_ids'] ?? []);
        $tagIds = array_map('intval', $rules['product_tag_ids'] ?? []);

        if ($productIds !== [] && ! in_array((int) ($context['product_id'] ?? 0), $productIds, true)) {
            return false;
        }

        if ($categoryIds !== []) {
            $categoryId = (int) ($context['category_id'] ?? 0);

            if ($categoryId <= 0 || ! in_array($categoryId, $categoryIds, true)) {
                return false;
            }
        }

        if ($tagIds !== []) {
            $productId = (int) ($context['product_id'] ?? 0);

            if ($productId <= 0) {
                return false;
            }

            $hasTag = Product::query()
                ->whereKey($productId)
                ->whereHas('tags', fn ($q) => $q->whereIn('product_tags.id', $tagIds))
                ->exists();

            if (! $hasTag) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function matchesTargeting(Campaign $campaign, array $context): bool
    {
        $segmentInput = [
            'visitor_key' => $context['visitor_key'] ?? null,
            'product_id' => $context['product_id'] ?? null,
            'category_id' => $context['category_id'] ?? null,
            'cart_product_ids' => $context['cart_product_ids'] ?? [],
            'fulfilment_method' => $context['fulfilment_method'] ?? null,
            'location_city' => $context['location_city'] ?? null,
            'location_zone' => $context['location_zone'] ?? null,
            'location_available' => $context['location_available'] ?? false,
        ];

        /** @var User|null $customer */
        $customer = $context['customer'] ?? null;

        return $this->evaluator->matchesGroups(
            is_array($campaign->targeting_rules) ? $campaign->targeting_rules : [],
            $context,
            fn (int $segmentId): bool => $this->segments->matchesCached($segmentId, $segmentInput, $customer),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function matchesFrequency(Campaign $campaign, array $context): bool
    {
        $policy = $campaign->frequency_policy instanceof CampaignFrequencyPolicy
            ? $campaign->frequency_policy
            : CampaignFrequencyPolicy::tryFrom((string) $campaign->frequency_policy);

        if ($policy === null) {
            return false;
        }

        $customerId = ($context['customer'] ?? null)?->getKey();
        $visitorKey = $context['visitor_key'] ?? null;
        $sessionKey = $context['session_key'] ?? null;

        $base = CampaignImpression::query()
            ->where('campaign_id', $campaign->id)
            ->where('event_type', CampaignImpressionEvent::Impression->value)
            ->when(
                $customerId !== null,
                fn ($q) => $q->where(function ($inner) use ($customerId, $visitorKey): void {
                    $inner->where('customer_id', $customerId);

                    // After visitor→customer claim, treat prior visitor impressions as the same actor.
                    if (is_string($visitorKey) && $visitorKey !== '') {
                        $inner->orWhere(function ($legacy) use ($visitorKey): void {
                            $legacy->whereNull('customer_id')->where('visitor_key', $visitorKey);
                        });
                    }
                }),
                fn ($q) => $q->where('visitor_key', $visitorKey)->whereNull('customer_id'),
            );

        return match ($policy) {
            CampaignFrequencyPolicy::EverySession => true,
            CampaignFrequencyPolicy::OncePerSession => $sessionKey === null
                || ! (clone $base)->where('session_key', $sessionKey)->exists(),
            CampaignFrequencyPolicy::OncePerActor => ! (clone $base)->exists(),
            CampaignFrequencyPolicy::OncePerDay => ! (clone $base)
                ->where('occurred_at', '>=', now()->startOfDay())
                ->exists(),
            CampaignFrequencyPolicy::Cooldown => $this->cooldownAllows($campaign, clone $base),
            CampaignFrequencyPolicy::MaxImpressions => $this->maxImpressionsAllows($campaign, clone $base),
        };
    }

    protected function cooldownAllows(Campaign $campaign, $query): bool
    {
        $hours = max(1, (int) ($campaign->cooldown_hours ?? 24));
        $latest = (clone $query)->orderByDesc('occurred_at')->value('occurred_at');

        if ($latest === null) {
            return true;
        }

        return Carbon::parse($latest)->lte(now()->subHours($hours));
    }

    protected function maxImpressionsAllows(Campaign $campaign, $query): bool
    {
        $max = max(1, (int) ($campaign->max_impressions ?? 1));

        return (clone $query)->count() < $max;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toPublicPayload(Campaign $campaign, string $requestId): array
    {
        return [
            'id' => $campaign->id,
            'attribution_key' => $campaign->attribution_key,
            'request_id' => $requestId,
            'surface' => $campaign->surface instanceof CampaignSurface
                ? $campaign->surface->value
                : (string) $campaign->surface,
            'title' => $campaign->title,
            'message' => $campaign->message,
            'image_url' => PublicMedia::url($campaign->image_path),
            'cta_label' => $campaign->cta_label,
            'cta' => [
                'type' => $campaign->cta_type instanceof CampaignCtaType
                    ? $campaign->cta_type->value
                    : (string) $campaign->cta_type,
                'product_id' => $campaign->cta_product_id,
                'category_id' => $campaign->cta_category_id,
                'promotion_id' => $campaign->cta_promotion_id,
                'internal_path' => $campaign->cta_internal_path,
            ],
            'trigger' => is_array($campaign->trigger_rules) ? $campaign->trigger_rules : [
                'type' => 'immediate',
            ],
            'placement_hint' => $campaign->placement_rules['placements'][0] ?? 'global',
        ];
    }
}
