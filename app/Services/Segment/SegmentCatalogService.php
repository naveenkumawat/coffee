<?php

namespace App\Services\Segment;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Models\AudienceSegment;
use App\Models\User;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SegmentCatalogService implements SegmentCatalogServiceInterface
{
    public function __construct(
        protected TargetingRuleValidator $validator,
        protected SegmentServiceInterface $segments,
    ) {}

    public function paginateForAdmin(?string $status = null): LengthAwarePaginator
    {
        return AudienceSegment::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(20);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?User $actor = null): AudienceSegment
    {
        $payload = $this->normalizePayload($data);
        $payload['created_by'] = $actor?->getKey();
        $payload['updated_by'] = $actor?->getKey();
        $payload['stable_key'] = 'seg_'.Str::lower(Str::random(16));

        $segment = AudienceSegment::query()->create($payload);
        $this->segments->flushMatchCache();

        return $segment;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AudienceSegment $segment, array $data, ?User $actor = null): AudienceSegment
    {
        $payload = $this->normalizePayload($data, $segment);
        $payload['updated_by'] = $actor?->getKey();

        $segment->update($payload);
        $this->segments->flushMatchCache();

        return $segment->fresh() ?? $segment;
    }

    public function delete(AudienceSegment $segment): void
    {
        $segment->delete();
        $this->segments->flushMatchCache();
    }

    public function setStatus(AudienceSegment $segment, AudienceSegmentStatus $status, ?User $actor = null): AudienceSegment
    {
        $segment->update([
            'status' => $status,
            'updated_by' => $actor?->getKey(),
        ]);
        $this->segments->flushMatchCache();

        return $segment->fresh() ?? $segment;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $data, ?AudienceSegment $existing = null): array
    {
        $rules = $this->validator->validateRuleGroups(
            is_array($data['rules'] ?? null) ? $data['rules'] : [],
            $this->validator->segmentRuleTypes(),
            'rules',
        );

        $name = trim((string) $data['name']);
        $slug = filled($data['slug'] ?? null)
            ? Str::slug((string) $data['slug'])
            : Str::slug($name);

        if ($slug === '') {
            $slug = 'segment-'.Str::lower(Str::random(6));
        }

        $uniqueSlug = $slug;
        $suffix = 1;

        while (
            AudienceSegment::query()
                ->where('slug', $uniqueSlug)
                ->when($existing, fn ($q) => $q->whereKeyNot($existing->getKey()))
                ->exists()
        ) {
            $uniqueSlug = $slug.'-'.$suffix;
            $suffix++;
        }

        return [
            'name' => $name,
            'slug' => $uniqueSlug,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'status' => AudienceSegmentStatus::from((string) $data['status']),
            'actor_scope' => AudienceSegmentActor::from((string) ($data['actor_scope'] ?? AudienceSegmentActor::Both->value)),
            'rules' => $rules,
        ];
    }
}
