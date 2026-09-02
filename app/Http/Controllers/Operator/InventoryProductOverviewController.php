<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\Reporting\InventoryProductReportingServiceInterface;
use Illuminate\Contracts\View\View;

class InventoryProductOverviewController extends Controller
{
    public function __construct(
        protected InventoryProductReportingServiceInterface $reporting,
    ) {}

    public function __invoke(): View
    {
        return view('operator.reports.inventory-products.index', [
            'overview' => $this->reporting->buildOperatorOverview(),
        ]);
    }
}
