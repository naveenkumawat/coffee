<?php

namespace App\Services\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface InventoryProductReportingServiceInterface
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
     * @param  array<string, mixed>  $filters
     */
    public function exportIngredientMovementsCsv(array $filters = []): StreamedResponse;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportProductSalesCsv(array $filters = []): StreamedResponse;
}
