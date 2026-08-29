<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\InventoryRefillRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryRefillRequestIndexRequest;
use App\Http\Requests\Inventory\InventoryRefillRequestReviewRequest;
use App\Models\InventoryRefillRequest;
use App\Parsers\Inventory\InventoryRefillRequestParserInterface;
use App\Repositories\Inventory\InventoryRefillRequestRepositoryInterface;
use App\Repositories\Inventory\InventoryRepositoryInterface;
use App\Services\Inventory\InventoryRefillRequestServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class InventoryRefillRequestController extends Controller
{
    public function __construct(
        protected InventoryRefillRequestParserInterface $parser,
        protected InventoryRefillRequestRepositoryInterface $requests,
        protected InventoryRepositoryInterface $inventory,
        protected InventoryRefillRequestServiceInterface $service,
    ) {}

    public function index(InventoryRefillRequestIndexRequest $request): View
    {
        $this->authorize('viewAny', InventoryRefillRequest::class);

        $filters = $this->parser->getFilterTransferFromArrayData($request->validated());

        return view('administrator.inventory.refill-requests.index', [
            'requests' => $this->requests->paginateForAdministrator($filters),
            'ingredientOptions' => $this->inventory->ingredientOptions(),
            'statusOptions' => InventoryRefillRequestStatus::options(),
            'requesterOptions' => $this->requests->requesterOptions(),
            'pendingCount' => $this->requests->countPending(),
        ]);
    }

    public function show(InventoryRefillRequest $inventoryRefillRequest): View
    {
        $this->authorize('view', $inventoryRefillRequest);

        return view('administrator.inventory.refill-requests.show', [
            'request' => $inventoryRefillRequest->load(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy']),
        ]);
    }

    public function approve(InventoryRefillRequestReviewRequest $reviewRequest, InventoryRefillRequest $inventoryRefillRequest): RedirectResponse
    {
        $this->authorize('review', $inventoryRefillRequest);

        $this->service->approve(
            $inventoryRefillRequest,
            $reviewRequest->user('admin'),
            $reviewRequest->validated('review_notes'),
        );

        return redirect()
            ->route('administrator.inventory.refill-requests.show', $inventoryRefillRequest)
            ->with('status', 'Refill request approved successfully.');
    }

    public function reject(InventoryRefillRequestReviewRequest $reviewRequest, InventoryRefillRequest $inventoryRefillRequest): RedirectResponse
    {
        $this->authorize('review', $inventoryRefillRequest);

        $this->service->reject(
            $inventoryRefillRequest,
            $reviewRequest->user('admin'),
            $reviewRequest->validated('review_notes'),
        );

        return redirect()
            ->route('administrator.inventory.refill-requests.show', $inventoryRefillRequest)
            ->with('status', 'Refill request rejected successfully.');
    }
}
