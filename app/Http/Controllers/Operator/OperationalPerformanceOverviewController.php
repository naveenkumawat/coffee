<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\Reporting\OperationalPerformanceReportingServiceInterface;
use Illuminate\Contracts\View\View;

class OperationalPerformanceOverviewController extends Controller
{
    public function __construct(
        protected OperationalPerformanceReportingServiceInterface $reporting,
    ) {}

    public function __invoke(): View
    {
        return view('operator.reports.operational-performance.index', [
            'overview' => $this->reporting->buildOperatorOverview(),
        ]);
    }
}
