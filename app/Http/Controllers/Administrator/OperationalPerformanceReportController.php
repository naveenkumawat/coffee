<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\OperationalPerformanceReportRequest;
use App\Services\Reporting\OperationalPerformanceReportingServiceInterface;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalPerformanceReportController extends Controller
{
    public function __construct(
        protected OperationalPerformanceReportingServiceInterface $reporting,
    ) {}

    public function index(OperationalPerformanceReportRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->reporting->buildAdminReport($filters);

        return view('administrator.reports.operational-performance.index', [
            'report' => $report,
            'filters' => [
                'preset' => $report['preset'],
                'from' => $filters['from'] ?? $report['start_local']->format('Y-m-d'),
                'to' => $filters['to'] ?? $report['end_local']->format('Y-m-d'),
                'section' => $report['section'],
                'station' => $report['filters']['station'],
                'channel' => $report['filters']['channel'],
                'product_category_id' => $report['filters']['product_category_id'],
                'product_type' => $report['filters']['product_type'],
            ],
        ]);
    }

    public function exportPreparations(OperationalPerformanceReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportPreparationPerformanceCsv($request->validated());
    }

    public function exportDining(OperationalPerformanceReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportDiningPerformanceCsv($request->validated());
    }
}
