<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\Reporting\FinancialReportingServiceInterface;
use Illuminate\Contracts\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        protected FinancialReportingServiceInterface $reporting,
    ) {}

    public function __invoke(): View
    {
        return view('operator.reconciliation.index', [
            'reconciliation' => $this->reporting->buildOperatorReconciliation(),
        ]);
    }
}
