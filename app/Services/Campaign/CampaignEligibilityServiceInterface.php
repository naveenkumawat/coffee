<?php

namespace App\Services\Campaign;

use App\Models\User;

interface CampaignEligibilityServiceInterface
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{request_id: string, campaign: array<string, mixed>|null}
     */
    public function eligible(array $input, ?User $customer = null): array;

    /**
     * @param  array<string, mixed>  $input
     * @return array{recorded: bool}
     */
    public function recordInteraction(array $input, ?User $customer = null): array;

    public function flushConfigCache(): void;
}
