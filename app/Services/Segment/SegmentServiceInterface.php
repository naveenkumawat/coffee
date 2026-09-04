<?php

namespace App\Services\Segment;

use App\Models\AudienceSegment;
use App\Models\User;

interface SegmentServiceInterface
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{matches: bool, segment_id: int, stable_key: string|null, reason?: string}
     */
    public function matches(AudienceSegment|int $segment, array $input = [], ?User $customer = null): array;

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{id: int, name: string, stable_key: string|null}>
     */
    public function matchingSegments(array $input = [], ?User $customer = null): array;

    /**
     * @param  array<string, mixed>  $input
     */
    public function matchesCached(int $segmentId, array $input = [], ?User $customer = null): bool;

    public function flushMatchCache(): void;

    /**
     * @return array{scanned: int, matched: int, capped: bool}
     */
    public function approximateCustomerMatchCount(AudienceSegment $segment, int $limit = 200): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function buildContext(array $input = [], ?User $customer = null): array;
}
