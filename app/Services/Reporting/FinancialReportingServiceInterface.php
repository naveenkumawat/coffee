<?php

namespace App\Services\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface FinancialReportingServiceInterface
{
    /**
     * @param  array{
     *     preset?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     channel?: string|null,
     *     payment_method?: string|null
     * }  $filters
     * @return array<string, mixed>
     */
    public function buildAdminReport(array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function buildOperatorReconciliation(): array;

    /**
     * @param  array{
     *     preset?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     channel?: string|null,
     *     payment_method?: string|null
     * }  $filters
     */
    public function exportAdminCsv(array $filters = []): StreamedResponse;
}
