<?php

namespace App\Services\Attribution;

interface AttributionAnalyticsServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildRecommendationReport(array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildCampaignReport(array $filters = []): array;
}
