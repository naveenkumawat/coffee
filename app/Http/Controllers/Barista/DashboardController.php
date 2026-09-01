<?php

namespace App\Http\Controllers\Barista;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Http\Controllers\Controller;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        CafeAvailabilityServiceInterface $cafeAvailability,
        OrderPreparationServiceInterface $preparations,
    ): View {
        $tickets = $preparations->queueForStation(PreparationStation::Bar);

        return view('barista.dashboard.index', [
            'cafeAvailability' => $cafeAvailability->status(),
            'pending' => $tickets->where('status', OrderPreparationStatus::Pending)->count(),
            'accepted' => $tickets->where('status', OrderPreparationStatus::Accepted)->count(),
            'preparing' => $tickets->where('status', OrderPreparationStatus::Preparing)->count(),
            'ready' => $tickets->where('status', OrderPreparationStatus::Ready)->count(),
        ]);
    }
}
