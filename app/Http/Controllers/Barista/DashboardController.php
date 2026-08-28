<?php

namespace App\Http\Controllers\Barista;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('barista.dashboard.index', [
            'activeShift' => [
                'station' => 'Main Espresso Bar',
                'started_at' => now()->format('g:i A'),
                'focus' => 'Morning rush preparation',
            ],
            'queue' => [
                ['ticket' => 'CC-1042', 'guest' => 'Walk-in order', 'status' => 'Preparing'],
                ['ticket' => 'CC-1043', 'guest' => 'Mobile pickup', 'status' => 'Queued'],
                ['ticket' => 'CC-1044', 'guest' => 'Delivery handoff', 'status' => 'Ready soon'],
            ],
        ]);
    }
}
