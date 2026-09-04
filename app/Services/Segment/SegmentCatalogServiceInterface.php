<?php

namespace App\Services\Segment;

use App\Enums\AudienceSegmentStatus;
use App\Models\AudienceSegment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SegmentCatalogServiceInterface
{
    public function paginateForAdmin(?string $status = null): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?User $actor = null): AudienceSegment;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AudienceSegment $segment, array $data, ?User $actor = null): AudienceSegment;

    public function delete(AudienceSegment $segment): void;

    public function setStatus(AudienceSegment $segment, AudienceSegmentStatus $status, ?User $actor = null): AudienceSegment;
}
