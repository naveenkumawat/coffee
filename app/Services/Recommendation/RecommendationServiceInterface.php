<?php

namespace App\Services\Recommendation;

use App\Models\User;

interface RecommendationServiceInterface
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     request_id: string,
     *     context: string,
     *     cold_start: bool,
     *     items: list<array{product: array<string, mixed>, reason: string, strategy: string, request_id: string}>
     * }
     */
    public function recommend(array $input, ?User $customer = null): array;
}
