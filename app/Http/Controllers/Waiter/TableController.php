<?php

namespace App\Http\Controllers\Waiter;

use App\Http\Controllers\Controller;
use App\Services\Dining\DiningSessionServiceInterface;
use Illuminate\Contracts\View\View;

class TableController extends Controller
{
    public function __construct(protected DiningSessionServiceInterface $dining) {}

    public function index(): View
    {
        return view('waiter.tables.index', [
            'tables' => $this->dining->tableOperationalStates(),
        ]);
    }
}
