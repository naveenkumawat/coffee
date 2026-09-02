<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\FinancialReportRequest;
use App\Services\Reporting\FinancialReportingServiceInterface;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(
        protected FinancialReportingServiceInterface $reporting,
    ) {}

    public function index(FinancialReportRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->reporting->buildAdminReport($filters);

        return view('administrator.reports.financial.index', [
            'report' => $report,
            'filters' => [
                'preset' => $report['preset'],
                'from' => $filters['from'] ?? $report['start_local']->format('Y-m-d'),
                'to' => $filters['to'] ?? $report['end_local']->format('Y-m-d'),
                'channel' => $report['channel'],
                'payment_method' => $report['payment_method'],
            ],
        ]);
    }

    public function export(FinancialReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportAdminCsv($request->validated());
    }
}
