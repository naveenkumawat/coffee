<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(DiningSessionServiceInterface $dining): View
    {
        $states = $dining->tableOperationalStates();

        return view('waiter.dashboard.index', [
            'occupied' => $states->where('state', 'occupied')->count(),
            'billRequested' => $states->where('state', 'bill_requested')->count(),
            'awaitingPayment' => $states->where('state', 'awaiting_payment')->count(),
            'available' => $states->where('state', 'available')->count(),
        ]);
    }
}
