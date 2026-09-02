<?php

namespace App\Services\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface OperationalPerformanceReportingServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function buildAdminReport(array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function buildOperatorOverview(): array;

    /**
     * Live station queue context (Barista BAR / Chef KITCHEN).
     *
     * @return array<string, mixed>
     */
    public function buildStationQueueContext(string $station): array;

    /**
     * Waiter dining timing context for a session.
     *
     * @return array<string, mixed>
     */
    public function buildWaiterSessionTiming(int $diningSessionId): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportPreparationPerformanceCsv(array $filters = []): StreamedResponse;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportDiningPerformanceCsv(array $filters = []): StreamedResponse;
}
