<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\AttributionAnalyticsRequest;
use App\Services\Attribution\AttributionAnalyticsServiceInterface;
use Illuminate\Contracts\View\View;

class RecommendationAnalyticsController extends Controller
{
    public function __construct(
        protected AttributionAnalyticsServiceInterface $analytics,
    ) {}

    public function index(AttributionAnalyticsRequest $request): View
    {
        $filters = $request->validated();
        $report = $this->analytics->buildRecommendationReport($filters);

        return view('administrator.reports.recommendations.index', [
            'report' => $report,
            'filters' => [
                'preset' => $report['preset'],
                'from' => $filters['from'] ?? $report['start_local']->format('Y-m-d'),
                'to' => $filters['to'] ?? $report['end_local']->format('Y-m-d'),
            ],
        ]);
    }
}
