<?php

namespace App\Services\PublicCache;

interface PublicCacheVersionServiceInterface
{
    /**
     * @return array{cache_version: string, catalog_version: string, content_version: string, media_version: string, invalidated_at: ?string, reason: ?string}
     */
    public function snapshot(): array;

    public function currentVersion(): string;

    public function bump(string $reason, ?int $actorId = null): string;

    public function forgetServerPublicCaches(): void;

    public function invalidate(string $reason, ?int $actorId = null): string;
}
