<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\InventoryProductReportRequest;
use App\Services\Reporting\InventoryProductReportingServiceInterface;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryProductReportController extends Controller
{
    public function __construct(
        protected InventoryProductReportingServiceInterface $reporting,
    ) {}

    public function index(InventoryProductReportRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->reporting->buildAdminReport($filters);

        return view('administrator.reports.inventory-products.index', [
            'report' => $report,
            'filters' => [
                'preset' => $report['preset'],
                'from' => $filters['from'] ?? $report['start_local']->format('Y-m-d'),
                'to' => $filters['to'] ?? $report['end_local']->format('Y-m-d'),
                'section' => $report['section'],
                'ingredient_id' => $report['filters']['ingredient_id'],
                'ingredient_category_id' => $report['filters']['ingredient_category_id'],
                'stock_status' => $report['filters']['stock_status'],
                'product_category_id' => $report['filters']['product_category_id'],
                'product_type' => $report['filters']['product_type'],
                'station' => $report['filters']['station'],
            ],
        ]);
    }

    public function exportIngredientMovements(InventoryProductReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportIngredientMovementsCsv($request->validated());
    }

    public function exportProductSales(InventoryProductReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportProductSalesCsv($request->validated());
    }
}
