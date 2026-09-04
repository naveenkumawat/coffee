<?php

namespace App\Services\Merchandising;

use App\Enums\CampaignSurface;
use App\Enums\HomeSectionPlacement;
use App\Enums\HomeSectionSourceType;
use App\Enums\RecommendationContext;
use App\Enums\UserRole;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\User;
use App\Services\Campaign\CampaignEligibilityServiceInterface;
use App\Services\Recommendation\RecommendationServiceInterface;
use App\Services\Segment\SegmentServiceInterface;
use App\Services\Targeting\TargetingRuleEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MerchandisingService implements MerchandisingServiceInterface
{
    public function __construct(
        protected RecommendationServiceInterface $recommendations,
        protected CampaignEligibilityServiceInterface $campaigns,
        protected SegmentServiceInterface $segments,
        protected TargetingRuleEvaluator $evaluator,
    ) {}

    /**
     * @param  array{
     *     placement?: string,
     *     visitor_key?: string|null,
     *     session_key?: string|null,
     *     fulfilment_method?: string|null,
     *     cart_product_ids?: list<int>
     * }  $input
     * @return array{
     *     placement: string,
     *     sections: list<array<string, mixed>>,
     *     campaigns: array{banner: array<string, mixed>|null, inline: array<string, mixed>|null}
     * }
     */
    public function landingPayload(array $input = [], ?User $customer = null): array
    {
        $customer = $customer !== null && $customer->hasRole(UserRole::Customer) ? $customer : null;
        $placement = HomeSectionPlacement::tryFrom((string) ($input['placement'] ?? HomeSectionPlacement::Home->value))
            ?? HomeSectionPlacement::Home;

        try {
            return $this->buildLandingPayload($input, $customer, $placement);
        } catch (Throwable $exception) {
            report($exception);

            return $this->genericFallbackPayload($placement);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     placement: string,
     *     sections: list<array<string, mixed>>,
     *     campaigns: array{banner: array<string, mixed>|null, inline: array<string, mixed>|null}
     * }
     */
    protected function buildLandingPayload(array $input, ?User $customer, HomeSectionPlacement $placement): array
    {
        $visitorKey = isset($input['visitor_key']) ? trim((string) $input['visitor_key']) : null;
        $visitorKey = $visitorKey !== '' ? $visitorKey : null;

        $context = $this->segments->buildContext([
            'visitor_key' => $visitorKey,
            'cart_product_ids' => $input['cart_product_ids'] ?? [],
            'fulfilment_method' => $input['fulfilment_method'] ?? null,
            'location_available' => false,
        ], $customer);

        $sectionsConfig = $this->activeSectionConfigs($placement);
        $curatedProductsBySection = $this->loadCuratedProducts(
            $sectionsConfig
                ->filter(fn (HomeSection $section): bool => $this->needsCuratedProducts($section))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );

        $seenProductIds = [];
        $sections = [];

        foreach ($sectionsConfig as $section) {
            if (! $this->isSchedulable($section)) {
                continue;
            }

            if (! $this->matchesTargeting($section, $context)) {
                continue;
            }

            if ($curatedProductsBySection->has((int) $section->id)) {
                $section->setRelation('products', $curatedProductsBySection->get((int) $section->id));
            } else {
                $section->setRelation('products', collect());
            }

            try {
                $resolved = $this->resolveSectionProducts($section, $input, $customer, $seenProductIds);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if ($resolved['products'] === []) {
                continue;
            }

            if ($section->dedupe_products) {
                foreach ($resolved['product_ids'] as $productId) {
                    $seenProductIds[$productId] = true;
                }
            }

            $sections[] = [
                'id' => (int) $section->id,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'slug' => $section->slug,
                'source_type' => $section->source_type instanceof HomeSectionSourceType
                    ? $section->source_type->value
                    : (string) $section->source_type,
                'placement' => $placement->value,
                'products' => $resolved['products'],
                'recommendation' => $resolved['recommendation'],
            ];
        }

        $campaignPlacement = $placement === HomeSectionPlacement::Menu ? 'menu' : 'home';

        return [
            'placement' => $placement->value,
            'sections' => $sections,
            'campaigns' => [
                'banner' => $this->eligibleCampaign(CampaignSurface::Banner, $campaignPlacement, $input, $customer),
                'inline' => $this->eligibleCampaign(CampaignSurface::Inline, $campaignPlacement, $input, $customer),
            ],
        ];
    }

    /**
     * @return array{
     *     placement: string,
     *     sections: list<array<string, mixed>>,
     *     campaigns: array{banner: null, inline: null}
     * }
     */
    protected function genericFallbackPayload(HomeSectionPlacement $placement): array
    {
        $sections = [];

        foreach ($this->activeSectionConfigs($placement) as $section) {
            $source = $section->source_type instanceof HomeSectionSourceType
                ? $section->source_type
                : HomeSectionSourceType::tryFrom((string) $section->source_type) ?? HomeSectionSourceType::Curated;

            if ($source !== HomeSectionSourceType::Curated) {
                continue;
            }

            if (! $this->isSchedulable($section)) {
                continue;
            }

            $products = $this->loadCuratedProducts([(int) $section->id])->get((int) $section->id, collect());
            $limit = $section->max_items !== null && (int) $section->max_items > 0
                ? (int) $section->max_items
                : 50;

            $payload = ProductResource::collection($products->take($limit)->values())->resolve();

            if ($payload === []) {
                continue;
            }

            $sections[] = [
                'id' => (int) $section->id,
                'title' => $section->title,
                'subtitle' => $section->subtitle,
                'slug' => $section->slug,
                'source_type' => HomeSectionSourceType::Curated->value,
                'placement' => $placement->value,
                'products' => $payload,
                'recommendation' => null,
            ];
        }

        return [
            'placement' => $placement->value,
            'sections' => $sections,
            'campaigns' => [
                'banner' => null,
                'inline' => null,
            ],
        ];
    }

    /**
     * @return Collection<int, HomeSection>
     */
    protected function activeSectionConfigs(HomeSectionPlacement $placement): Collection
    {
        $ttl = max(30, (int) config('coffee.behaviour.merchandising.config_cache_ttl_seconds', 120));
        $key = 'merchandising.sections.'.$placement->value.'.v1';

        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember($key, $ttl, function () use ($placement): array {
            return HomeSection::query()
                ->where('is_active', true)
                ->where('placement', $placement->value)
                ->orderByDesc('priority')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get([
                    'id',
                    'name',
                    'title',
                    'slug',
                    'subtitle',
                    'sort_order',
                    'is_active',
                    'max_items',
                    'placement',
                    'source_type',
                    'source_category_id',
                    'source_tag_id',
                    'recommendation_context',
                    'priority',
                    'targeting_rules',
                    'starts_at',
                    'ends_at',
                    'dedupe_products',
                    'fallback_to_curated',
                ])
                ->map(function (HomeSection $section): array {
                    $row = $section->toArray();
                    $row['specificity'] = $this->targetingSpecificity(is_array($section->targeting_rules) ? $section->targeting_rules : []);

                    return $row;
                })
                ->sort(function (array $a, array $b): int {
                    $priority = ((int) ($b['priority'] ?? 0)) <=> ((int) ($a['priority'] ?? 0));

                    if ($priority !== 0) {
                        return $priority;
                    }

                    $specificity = ((int) ($b['specificity'] ?? 0)) <=> ((int) ($a['specificity'] ?? 0));

                    if ($specificity !== 0) {
                        return $specificity;
                    }

                    $sort = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));

                    if ($sort !== 0) {
                        return $sort;
                    }

                    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
                })
                ->values()
                ->all();
        });

        return collect($rows)->map(function (array $row): HomeSection {
            unset($row['specificity']);
            $section = new HomeSection;
            $section->forceFill($row);
            $section->exists = true;

            return $section;
        });
    }

    public function flushConfigCache(): void
    {
        foreach (HomeSectionPlacement::cases() as $placement) {
            Cache::forget('merchandising.sections.'.$placement->value.'.v1');
        }
    }

    /**
     * @param  array{all?: list<mixed>, any?: list<mixed>, exclude?: list<mixed>}  $rules
     */
    protected function targetingSpecificity(array $rules): int
    {
        $all = is_array($rules['all'] ?? null) ? $rules['all'] : [];
        $any = is_array($rules['any'] ?? null) ? $rules['any'] : [];
        $exclude = is_array($rules['exclude'] ?? null) ? $rules['exclude'] : [];

        return count($all) + count($any) + count($exclude);
    }

    protected function needsCuratedProducts(HomeSection $section): bool
    {
        $source = $section->source_type instanceof HomeSectionSourceType
            ? $section->source_type
            : HomeSectionSourceType::tryFrom((string) $section->source_type) ?? HomeSectionSourceType::Curated;

        return $source === HomeSectionSourceType::Curated || (bool) $section->fallback_to_curated;
    }

    /**
     * @param  list<int>  $sectionIds
     * @return Collection<int, Collection<int, Product>>
     */
    protected function loadCuratedProducts(array $sectionIds): Collection
    {
        $sectionIds = array_values(array_unique(array_filter(array_map('intval', $sectionIds))));

        if ($sectionIds === []) {
            return collect();
        }

        $sections = HomeSection::query()
            ->whereKey($sectionIds)
            ->with([
                'products' => function ($query): void {
                    $query
                        ->where('products.is_active', true)
                        ->where('products.is_available', true)
                        ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true))
                        ->with([
                            'category',
                            'flavours',
                            'tags' => fn ($tagQuery) => $tagQuery->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                            'defaultVariant.recipe.lines' => fn ($lineQuery) => $lineQuery
                                ->where('show_to_customer', true)
                                ->orderBy('sort_order')
                                ->orderBy('id'),
                            'defaultVariant.recipe.lines.ingredient',
                            'variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->where('is_available', true),
                            'variants.recipe.lines' => fn ($lineQuery) => $lineQuery
                                ->where('show_to_customer', true)
                                ->orderBy('sort_order')
                                ->orderBy('id'),
                            'variants.recipe.lines.ingredient',
                        ])
                        ->withAvg('ratings as ratings_avg_rating', 'rating')
                        ->withCount('ratings')
                        ->orderBy('home_section_products.sort_order')
                        ->orderBy('products.name');
                },
            ])
            ->get()
            ->keyBy(fn (HomeSection $section): int => (int) $section->id);

        return $sections->map(fn (HomeSection $section) => $section->products);
    }

    protected function isSchedulable(HomeSection $section): bool
    {
        $now = now();

        if ($section->starts_at !== null && $now->lt($section->starts_at)) {
            return false;
        }

        if ($section->ends_at !== null && $now->gt($section->ends_at)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function matchesTargeting(HomeSection $section, array $context): bool
    {
        $rules = is_array($section->targeting_rules) ? $section->targeting_rules : [];

        if ($rules === [] || (
            ($rules['all'] ?? []) === []
            && ($rules['any'] ?? []) === []
            && ($rules['exclude'] ?? []) === []
        )) {
            return true;
        }

        $input = [
            'visitor_key' => $context['visitor_key'] ?? null,
        ];

        /** @var User|null $customer */
        $customer = $context['customer'] ?? null;

        return $this->evaluator->matchesGroups(
            $rules,
            $context,
            fn (int $segmentId): bool => $this->segments->matchesCached($segmentId, $input, $customer),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<int, true>  $seenProductIds
     * @return array{
     *     products: list<array<string, mixed>>,
     *     product_ids: list<int>,
     *     recommendation: array{request_id: string, context: string, cold_start: bool}|null
     * }
     */
    protected function resolveSectionProducts(
        HomeSection $section,
        array $input,
        ?User $customer,
        array $seenProductIds,
    ): array {
        $source = $section->source_type instanceof HomeSectionSourceType
            ? $section->source_type
            : HomeSectionSourceType::tryFrom((string) $section->source_type) ?? HomeSectionSourceType::Curated;

        $limit = $section->max_items !== null && (int) $section->max_items > 0
            ? (int) $section->max_items
            : 12;

        $recommendationMeta = null;
        $products = collect();

        if ($source === HomeSectionSourceType::Curated) {
            $products = collect($section->products);
        } elseif ($source === HomeSectionSourceType::Category && $section->source_category_id) {
            $products = $this->catalogueProducts(
                ['product_category_id' => (int) $section->source_category_id],
                $limit + count($seenProductIds),
            );
        } elseif ($source === HomeSectionSourceType::Tag && $section->source_tag_id) {
            $products = $this->catalogueProducts(
                ['tag_id' => (int) $section->source_tag_id],
                $limit + count($seenProductIds),
            );
        } elseif ($source->isRecommendationBacked()) {
            $context = RecommendationContext::tryFrom((string) ($section->recommendation_context ?: (
                ($input['placement'] ?? 'home') === 'menu'
                    ? RecommendationContext::Menu->value
                    : RecommendationContext::Home->value
            ))) ?? RecommendationContext::Home;

            $recommendInput = [
                'context' => $context->value,
                'visitor_key' => $input['visitor_key'] ?? null,
                'cart_product_ids' => $input['cart_product_ids'] ?? [],
                'exclude_product_ids' => array_map('intval', array_keys($seenProductIds)),
                'limit' => max($limit, 8),
            ];

            if ($source !== HomeSectionSourceType::Recommendation) {
                $recommendInput['strategies'] = [$source->value];
            }

            $result = $this->recommendations->recommend($recommendInput, $customer);

            $items = collect($result['items'] ?? []);

            if ($source !== HomeSectionSourceType::Recommendation) {
                $strategyKey = $source->value;
                $items = $items->filter(fn (array $item): bool => ($item['strategy'] ?? '') === $strategyKey)->values();
            }

            $recommendationMeta = [
                'request_id' => (string) ($result['request_id'] ?? ''),
                'context' => (string) ($result['context'] ?? $context->value),
                'cold_start' => (bool) ($result['cold_start'] ?? true),
            ];

            $products = $items->map(function (array $item) use ($recommendationMeta): array {
                $product = $item['product'];
                $product['_attribution'] = [
                    'source_type' => 'recommendation',
                    'request_id' => $item['request_id'] ?? $recommendationMeta['request_id'],
                    'strategy' => $item['strategy'] ?? null,
                    'reason' => $item['reason'] ?? null,
                    'placement' => 'merchandising_section',
                ];

                return $product;
            });

            if ($products->isEmpty() && $section->fallback_to_curated) {
                $products = collect($section->products);
                $recommendationMeta = null;
            }
        }

        $payload = [];
        $ids = [];

        foreach ($products as $product) {
            if ($product instanceof Product) {
                $id = (int) $product->getKey();
                $row = ProductResource::make($product)->resolve();
            } elseif (is_array($product)) {
                $id = (int) ($product['id'] ?? 0);
                $row = $product;
            } else {
                continue;
            }

            if ($id <= 0) {
                continue;
            }

            if ($section->dedupe_products && isset($seenProductIds[$id])) {
                continue;
            }

            if (isset($ids[$id])) {
                continue;
            }

            $attribution = $row['_attribution'] ?? null;
            unset($row['_attribution']);

            if (is_array($attribution)) {
                $row['attribution'] = $attribution;
            }

            $payload[] = $row;
            $ids[$id] = true;

            if (count($payload) >= $limit) {
                break;
            }
        }

        return [
            'products' => $payload,
            'product_ids' => array_map('intval', array_keys($ids)),
            'recommendation' => $recommendationMeta,
        ];
    }

    /**
     * @param  array{product_category_id?: int, tag_id?: int}  $filters
     * @return Collection<int, Product>
     */
    protected function catalogueProducts(array $filters, int $limit): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->when(isset($filters['product_category_id']), fn ($q) => $q->where('product_category_id', $filters['product_category_id']))
            ->when(isset($filters['tag_id']), fn ($q) => $q->whereHas(
                'tags',
                fn ($t) => $t->where('product_tags.id', $filters['tag_id'])->where('is_active', true),
            ))
            ->with([
                'category',
                'flavours',
                'tags' => fn ($tagQuery) => $tagQuery->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'defaultVariant',
                'variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->where('is_available', true),
            ])
            ->withAvg('ratings as ratings_avg_rating', 'rating')
            ->withCount('ratings')
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->limit(max(1, $limit))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    protected function eligibleCampaign(
        CampaignSurface $surface,
        string $placement,
        array $input,
        ?User $customer,
    ): ?array {
        $result = $this->campaigns->eligible([
            'placement' => $placement,
            'surface' => $surface->value,
            'visitor_key' => $input['visitor_key'] ?? null,
            'session_key' => $input['session_key'] ?? null,
            'cart_product_ids' => $input['cart_product_ids'] ?? [],
            'fulfilment_method' => $input['fulfilment_method'] ?? null,
        ], $customer);

        return is_array($result['campaign'] ?? null) ? $result['campaign'] : null;
    }
}
