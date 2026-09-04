<?php

namespace App\Services\Loyalty;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface LoyaltyReportingServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildOperationsDashboard(array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateLedger(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdjustments(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * @return array<string, mixed>
     */
    public function customerLoyaltyDetail(User $customer): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportLedgerCsv(array $filters = []): StreamedResponse;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportBalancesCsv(array $filters = []): StreamedResponse;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportRedemptionsCsv(array $filters = []): StreamedResponse;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     timezone: string,
     *     preset: string,
     *     start_local: CarbonImmutable,
     *     end_local: CarbonImmutable,
     *     start_utc: CarbonImmutable,
     *     end_utc: CarbonImmutable
     * }
     */
    public function resolvePeriod(array $filters): array;

    /**
     * @return array<string, string>
     */
    public function definitions(): array;
}
