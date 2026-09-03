<?php

namespace App\Services\Campaign;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Models\Campaign;
use App\Models\User;
use App\Support\PublicMedia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CampaignCatalogService implements CampaignCatalogServiceInterface
{
    public function __construct(
        protected CampaignRuleValidator $validator,
        protected CampaignEligibilityServiceInterface $eligibility,
    ) {}

    public function paginateForAdmin(?string $status = null): LengthAwarePaginator
    {
        return Campaign::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?User $actor = null, ?UploadedFile $image = null): Campaign
    {
        $payload = $this->normalizePayload($data);
        $payload['attribution_key'] = 'cmp_'.Str::lower(Str::random(16));
        $payload['created_by'] = $actor?->getKey();
        $payload['updated_by'] = $actor?->getKey();

        if ($image !== null) {
            $payload['image_path'] = PublicMedia::store($image, PublicMedia::DIRECTORY_WEBSITE);
        }

        $campaign = Campaign::query()->create($payload);
        $this->eligibility->flushConfigCache();

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data, ?User $actor = null, ?UploadedFile $image = null, bool $removeImage = false): Campaign
    {
        $payload = $this->normalizePayload($data);
        $payload['updated_by'] = $actor?->getKey();
        $previous = $campaign->image_path;

        if ($image !== null) {
            $payload['image_path'] = PublicMedia::store($image, PublicMedia::DIRECTORY_WEBSITE);
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);
        } elseif ($removeImage) {
            $payload['image_path'] = null;
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);
        }

        $campaign->update($payload);
        $this->eligibility->flushConfigCache();

        return $campaign->fresh() ?? $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        PublicMedia::deleteManaged(is_string($campaign->image_path) ? $campaign->image_path : null);
        $campaign->delete();
        $this->eligibility->flushConfigCache();
    }

    public function setStatus(Campaign $campaign, CampaignStatus $status, ?User $actor = null): Campaign
    {
        $campaign->update([
            'status' => $status,
            'updated_by' => $actor?->getKey(),
        ]);
        $this->eligibility->flushConfigCache();

        return $campaign->fresh() ?? $campaign;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data): array
    {
        $placement = $this->validator->validatePlacementRules(
            is_array($data['placement_rules'] ?? null) ? $data['placement_rules'] : [],
        );
        $targeting = $this->validator->validateTargetingRules(
            is_array($data['targeting_rules'] ?? null) ? $data['targeting_rules'] : [],
        );
        $trigger = $this->validator->validateTriggerRules(
            is_array($data['trigger_rules'] ?? null) ? $data['trigger_rules'] : [],
        );

        $this->validator->assertCtaPayload($data);

        $frequency = CampaignFrequencyPolicy::from((string) $data['frequency_policy']);

        return [
            'name' => trim((string) $data['name']),
            'internal_label' => filled($data['internal_label'] ?? null) ? trim((string) $data['internal_label']) : null,
            'status' => CampaignStatus::from((string) $data['status']),
            'surface' => CampaignSurface::from((string) ($data['surface'] ?? CampaignSurface::Popup->value)),
            'title' => trim((string) $data['title']),
            'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
            'cta_label' => filled($data['cta_label'] ?? null) ? trim((string) $data['cta_label']) : null,
            'cta_type' => CampaignCtaType::from((string) $data['cta_type']),
            'cta_product_id' => ($data['cta_type'] ?? null) === CampaignCtaType::Product->value
                ? (int) $data['cta_product_id']
                : null,
            'cta_category_id' => ($data['cta_type'] ?? null) === CampaignCtaType::Category->value
                ? (int) $data['cta_category_id']
                : null,
            'cta_promotion_id' => ($data['cta_type'] ?? null) === CampaignCtaType::Promotion->value
                ? (int) $data['cta_promotion_id']
                : null,
            'cta_internal_path' => ($data['cta_type'] ?? null) === CampaignCtaType::InternalPage->value
                ? trim((string) $data['cta_internal_path'])
                : null,
            'priority' => max(0, (int) ($data['priority'] ?? 0)),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'frequency_policy' => $frequency,
            'cooldown_hours' => $frequency === CampaignFrequencyPolicy::Cooldown
                ? max(1, (int) ($data['cooldown_hours'] ?? 24))
                : null,
            'max_impressions' => $frequency === CampaignFrequencyPolicy::MaxImpressions
                ? max(1, (int) ($data['max_impressions'] ?? 1))
                : null,
            'placement_rules' => $placement,
            'targeting_rules' => $targeting,
            'trigger_rules' => $trigger,
        ];
    }
}
