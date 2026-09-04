<?php

namespace App\Services\Campaign;

use App\Enums\AudienceSegmentStatus;
use App\Enums\CampaignCtaType;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignTriggerType;
use App\Models\AudienceSegment;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Validation\ValidationException;

class CampaignRuleValidator
{
    public function __construct(
        protected TargetingRuleValidator $targeting,
    ) {}

    /**
     * @return list<string>
     */
    public function allowedRuleTypes(): array
    {
        return $this->targeting->campaignRuleTypes();
    }

    /**
     * @return list<string>
     */
    public function allowedOperators(): array
    {
        return $this->targeting->allowedOperators();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}
     */
    public function validateTargetingRules(array $rules): array
    {
        $normalized = $this->targeting->validateRuleGroups(
            $rules,
            $this->allowedRuleTypes(),
            'targeting_rules',
        );

        $this->assertSegmentReferencesExist($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{
     *     placements: list<string>,
     *     category_ids: list<int>,
     *     product_ids: list<int>,
     *     product_tag_ids: list<int>
     * }
     */
    public function validatePlacementRules(array $rules): array
    {
        $placements = $rules['placements'] ?? ['global'];

        if (! is_array($placements) || $placements === []) {
            throw ValidationException::withMessages([
                'placement_rules.placements' => 'At least one placement is required.',
            ]);
        }

        $allowed = CampaignPlacement::values();
        $cleanPlacements = [];

        foreach ($placements as $placement) {
            $placement = (string) $placement;

            if (! in_array($placement, $allowed, true)) {
                throw ValidationException::withMessages([
                    'placement_rules.placements' => 'Invalid placement: '.$placement,
                ]);
            }

            $cleanPlacements[] = $placement;
        }

        return [
            'placements' => array_values(array_unique($cleanPlacements)),
            'category_ids' => $this->normalizeIdList($rules['category_ids'] ?? [], 'placement_rules.category_ids'),
            'product_ids' => $this->normalizeIdList($rules['product_ids'] ?? [], 'placement_rules.product_ids'),
            'product_tag_ids' => $this->normalizeIdList($rules['product_tag_ids'] ?? [], 'placement_rules.product_tag_ids'),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{
     *     type: string,
     *     delay_ms: int|null,
     *     scroll_percent: int|null,
     *     product_view_count: int|null
     * }
     */
    public function validateTriggerRules(array $rules): array
    {
        $type = (string) ($rules['type'] ?? CampaignTriggerType::Immediate->value);

        if (! in_array($type, CampaignTriggerType::values(), true)) {
            throw ValidationException::withMessages([
                'trigger_rules.type' => 'Invalid trigger type.',
            ]);
        }

        $delay = isset($rules['delay_ms']) ? (int) $rules['delay_ms'] : null;
        $scroll = isset($rules['scroll_percent']) ? (int) $rules['scroll_percent'] : null;
        $views = isset($rules['product_view_count']) ? (int) $rules['product_view_count'] : null;

        if ($type === CampaignTriggerType::Delay->value && ($delay === null || $delay < 0 || $delay > 60000)) {
            throw ValidationException::withMessages([
                'trigger_rules.delay_ms' => 'Delay must be between 0 and 60000 ms.',
            ]);
        }

        if ($type === CampaignTriggerType::Scroll->value && ($scroll === null || $scroll < 10 || $scroll > 100)) {
            throw ValidationException::withMessages([
                'trigger_rules.scroll_percent' => 'Scroll percent must be between 10 and 100.',
            ]);
        }

        if ($type === CampaignTriggerType::ProductViews->value && ($views === null || $views < 1 || $views > 20)) {
            throw ValidationException::withMessages([
                'trigger_rules.product_view_count' => 'Product view count must be between 1 and 20.',
            ]);
        }

        return [
            'type' => $type,
            'delay_ms' => $type === CampaignTriggerType::Delay->value ? $delay : null,
            'scroll_percent' => $type === CampaignTriggerType::Scroll->value ? $scroll : null,
            'product_view_count' => $type === CampaignTriggerType::ProductViews->value ? $views : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function assertCtaPayload(array $payload): void
    {
        $type = CampaignCtaType::tryFrom((string) ($payload['cta_type'] ?? ''));

        if ($type === null) {
            throw ValidationException::withMessages([
                'cta_type' => 'Invalid CTA type.',
            ]);
        }

        match ($type) {
            CampaignCtaType::Product => $this->requirePositiveInt($payload['cta_product_id'] ?? null, 'cta_product_id'),
            CampaignCtaType::Category => $this->requirePositiveInt($payload['cta_category_id'] ?? null, 'cta_category_id'),
            CampaignCtaType::Promotion => $this->requirePositiveInt($payload['cta_promotion_id'] ?? null, 'cta_promotion_id'),
            CampaignCtaType::InternalPage => $this->assertInternalPath($payload['cta_internal_path'] ?? null),
            CampaignCtaType::Close => null,
        };
    }

    /**
     * @param  array{all: list<array<string, mixed>>, any: list<array<string, mixed>>, exclude: list<array<string, mixed>>}  $rules
     */
    protected function assertSegmentReferencesExist(array $rules): void
    {
        $ids = [];

        foreach (['all', 'any', 'exclude'] as $group) {
            foreach ($rules[$group] as $rule) {
                if (in_array($rule['type'], ['segment_matches', 'segment_not_matches'], true)) {
                    $ids[] = (int) $rule['value'];
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return;
        }

        $activeCount = AudienceSegment::query()
            ->whereIn('id', $ids)
            ->where('status', AudienceSegmentStatus::Active->value)
            ->count();

        if ($activeCount !== count($ids)) {
            throw ValidationException::withMessages([
                'targeting_rules' => 'Campaigns may only reference active audience segments.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    protected function normalizeIdList(mixed $ids, string $field): array
    {
        if ($ids === null || $ids === []) {
            return [];
        }

        if (! is_array($ids)) {
            throw ValidationException::withMessages([
                $field => 'Must be a list of ids.',
            ]);
        }

        $clean = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                throw ValidationException::withMessages([
                    $field => 'Ids must be positive integers.',
                ]);
            }

            $clean[] = $id;
        }

        return array_values(array_unique($clean));
    }

    protected function requirePositiveInt(mixed $value, string $field): void
    {
        if ((int) $value <= 0) {
            throw ValidationException::withMessages([
                $field => 'A valid target id is required for this CTA.',
            ]);
        }
    }

    protected function assertInternalPath(mixed $path): void
    {
        $path = is_string($path) ? trim($path) : '';

        if ($path === '' || ! str_starts_with($path, '/') || str_contains($path, '://') || str_contains($path, '//')) {
            throw ValidationException::withMessages([
                'cta_internal_path' => 'Internal path must be a relative app path starting with /.',
            ]);
        }

        if (! preg_match('#^/[a-z0-9/_\\-?=&]*$#i', $path)) {
            throw ValidationException::withMessages([
                'cta_internal_path' => 'Internal path contains invalid characters.',
            ]);
        }
    }
}
