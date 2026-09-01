<?php

namespace App\Http\Controllers\Operator;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Http\Controllers\Controller;
use App\Models\OrderPreparation;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class PreparationController extends Controller
{
    public function __construct(
        protected OrderPreparationServiceInterface $preparations,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', OrderPreparation::class);

        $barTickets = $this->preparations->queueForStation(PreparationStation::Bar);
        $kitchenTickets = $this->preparations->queueForStation(PreparationStation::Kitchen);

        return view('operator.preparations.index', [
            'barColumns' => $this->groupByStatus($barTickets),
            'kitchenColumns' => $this->groupByStatus($kitchenTickets),
        ]);
    }

    /**
     * @param  Collection<int, OrderPreparation>  $tickets
     * @return array<string, Collection<int, OrderPreparation>>
     */
    protected function groupByStatus($tickets): array
    {
        return [
            OrderPreparationStatus::Pending->value => $tickets->where('status', OrderPreparationStatus::Pending)->values(),
            OrderPreparationStatus::Accepted->value => $tickets->where('status', OrderPreparationStatus::Accepted)->values(),
            OrderPreparationStatus::Preparing->value => $tickets->where('status', OrderPreparationStatus::Preparing)->values(),
            OrderPreparationStatus::Ready->value => $tickets->where('status', OrderPreparationStatus::Ready)->values(),
        ];
    }
}
