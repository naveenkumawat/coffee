<?php

namespace App\Http\Controllers\Chef;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Http\Controllers\Controller;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(OrderPreparationServiceInterface $preparations): View
    {
        $tickets = $preparations->queueForStation(PreparationStation::Kitchen);

        return view('chef.dashboard.index', [
            'pending' => $tickets->where('status', OrderPreparationStatus::Pending)->count(),
            'accepted' => $tickets->where('status', OrderPreparationStatus::Accepted)->count(),
            'preparing' => $tickets->where('status', OrderPreparationStatus::Preparing)->count(),
            'ready' => $tickets->where('status', OrderPreparationStatus::Ready)->count(),
        ]);
    }
}
