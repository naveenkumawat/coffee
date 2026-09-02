<?php

namespace App\Http\Controllers\Operator;

use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PreparationStation;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Services\Reporting\FinancialReportingServiceInterface;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        DiningSessionServiceInterface $dining,
        OrderPreparationServiceInterface $preparations,
        FinancialReportingServiceInterface $reporting,
    ): View {
        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->whereIn('status', [
                OrderStatus::PaymentConfirmed->value,
                OrderStatus::Accepted->value,
                OrderStatus::Preparing->value,
                OrderStatus::ReadyForPickup->value,
            ])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $barQueue = $preparations->queueForStation(PreparationStation::Bar);
        $kitchenQueue = $preparations->queueForStation(PreparationStation::Kitchen);
        $states = $dining->tableOperationalStates();
        $reconciliation = $reporting->buildOperatorReconciliation();

        return view('operator.dashboard.index', [
            'newOrders' => (int) ($statusCounts[OrderStatus::PaymentConfirmed->value] ?? 0),
            'acceptedOrders' => (int) ($statusCounts[OrderStatus::Accepted->value] ?? 0),
            'preparingOrders' => (int) ($statusCounts[OrderStatus::Preparing->value] ?? 0),
            'readyOrders' => (int) ($statusCounts[OrderStatus::ReadyForPickup->value] ?? 0),
            'barQueueActive' => $barQueue
                ->whereIn('status', [OrderPreparationStatus::Pending, OrderPreparationStatus::Preparing, OrderPreparationStatus::Accepted])
                ->count(),
            'kitchenQueueActive' => $kitchenQueue
                ->whereIn('status', [OrderPreparationStatus::Pending, OrderPreparationStatus::Preparing, OrderPreparationStatus::Accepted])
                ->count(),
            'diningActive' => $states->whereIn('state', ['occupied', 'bill_requested', 'awaiting_payment'])->count(),
            'billRequested' => $states->where('state', 'bill_requested')->count(),
            'reconciliation' => $reconciliation,
        ]);
    }
}
