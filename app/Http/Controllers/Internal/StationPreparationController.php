<?php

namespace App\Http\Controllers\Internal;

use App\Enums\OrderPreparationStatus;
use App\Enums\PreparationStation;
use App\Http\Controllers\Controller;
use App\Models\OrderPreparation;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class StationPreparationController extends Controller
{
    public function __construct(
        protected OrderPreparationServiceInterface $preparations,
    ) {}

    abstract protected function station(): PreparationStation;

    abstract protected function panel(): string;

    abstract protected function indexRouteName(): string;

    abstract protected function acceptRouteName(): string;

    abstract protected function preparingRouteName(): string;

    abstract protected function readyRouteName(): string;

    public function index(): View
    {
        $this->authorize('viewAny', OrderPreparation::class);

        $station = $this->station();
        $tickets = $this->preparations->queueForStation($station);

        return view('internal.preparation.queue', [
            'panel' => $this->panel(),
            'station' => $station,
            'columns' => [
                OrderPreparationStatus::Pending->value => $tickets->where('status', OrderPreparationStatus::Pending)->values(),
                OrderPreparationStatus::Accepted->value => $tickets->where('status', OrderPreparationStatus::Accepted)->values(),
                OrderPreparationStatus::Preparing->value => $tickets->where('status', OrderPreparationStatus::Preparing)->values(),
                OrderPreparationStatus::Ready->value => $tickets->where('status', OrderPreparationStatus::Ready)->values(),
            ],
            'acceptRouteName' => $this->acceptRouteName(),
            'preparingRouteName' => $this->preparingRouteName(),
            'readyRouteName' => $this->readyRouteName(),
            'canTransition' => true,
            'orderShowRouteName' => $this->orderShowRouteName(),
        ]);
    }

    public function accept(Request $request, OrderPreparation $orderPreparation): RedirectResponse
    {
        return $this->transitionTicket($request, $orderPreparation, OrderPreparationStatus::Accepted);
    }

    public function preparing(Request $request, OrderPreparation $orderPreparation): RedirectResponse
    {
        return $this->transitionTicket($request, $orderPreparation, OrderPreparationStatus::Preparing);
    }

    public function ready(Request $request, OrderPreparation $orderPreparation): RedirectResponse
    {
        return $this->transitionTicket($request, $orderPreparation, OrderPreparationStatus::Ready);
    }

    protected function transitionTicket(
        Request $request,
        OrderPreparation $orderPreparation,
        OrderPreparationStatus $next,
    ): RedirectResponse {
        $this->authorize('transition', $orderPreparation);

        abort_unless(
            $orderPreparation->station === $this->station(),
            404,
        );

        $this->preparations->transition(
            $orderPreparation,
            $request->user('admin'),
            $next,
        );

        return redirect()
            ->route($this->indexRouteName())
            ->with('status', 'Preparation ticket updated to '.$next->label().'.');
    }

    protected function orderShowRouteName(): ?string
    {
        return null;
    }
}
