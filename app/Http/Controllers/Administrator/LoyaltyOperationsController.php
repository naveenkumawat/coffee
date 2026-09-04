<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\LoyaltyReportRequest;
use App\Models\LoyaltyReward;
use App\Services\Loyalty\LoyaltyReportingServiceInterface;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoyaltyOperationsController extends Controller
{
    public function __construct(
        protected LoyaltyReportingServiceInterface $reporting,
    ) {}

    public function index(LoyaltyReportRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->reporting->buildOperationsDashboard($filters);

        return view('administrator.loyalty-operations.index', [
            'report' => $report,
            'filters' => $this->filterState($filters, $report),
            'rewardOptions' => $this->rewardOptions(),
        ]);
    }

    public function ledger(LoyaltyReportRequest $request): View
    {
        $filters = $request->validated();
        $period = $this->reporting->resolvePeriod($filters);

        return view('administrator.loyalty-operations.ledger', [
            'transactions' => $this->reporting->paginateLedger($filters),
            'filters' => $this->filterState($filters, $period),
            'definitions' => $this->reporting->definitions(),
            'rewardOptions' => $this->rewardOptions(),
        ]);
    }

    public function adjustments(LoyaltyReportRequest $request): View
    {
        $filters = $request->validated();
        $period = $this->reporting->resolvePeriod($filters);

        return view('administrator.loyalty-operations.adjustments', [
            'transactions' => $this->reporting->paginateAdjustments($filters),
            'filters' => $this->filterState($filters, $period),
            'definitions' => $this->reporting->definitions(),
        ]);
    }

    public function exportLedger(LoyaltyReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportLedgerCsv($request->validated());
    }

    public function exportBalances(LoyaltyReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportBalancesCsv($request->validated());
    }

    public function exportRedemptions(LoyaltyReportRequest $request): StreamedResponse
    {
        return $this->reporting->exportRedemptionsCsv($request->validated());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $period
     * @return array<string, mixed>
     */
    protected function filterState(array $filters, array $period): array
    {
        return [
            'preset' => $period['preset'] ?? ($filters['preset'] ?? 'last_7_days'),
            'from' => $filters['from'] ?? ($period['start_local']?->format('Y-m-d') ?? ''),
            'to' => $filters['to'] ?? ($period['end_local']?->format('Y-m-d') ?? ''),
            'transaction_type' => $filters['transaction_type'] ?? 'all',
            'reward_id' => $filters['reward_id'] ?? null,
            'q' => $filters['q'] ?? '',
            'balance_filter' => $filters['balance_filter'] ?? 'all',
            'timezone' => $period['timezone'] ?? null,
            'start_local' => $period['start_local'] ?? null,
            'end_local' => $period['end_local'] ?? null,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function rewardOptions(): array
    {
        return LoyaltyReward::withTrashed()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
