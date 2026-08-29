<?php

namespace App\Http\Controllers\Barista;

use App\Enums\InventoryRefillRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryRefillRequestIndexRequest;
use App\Http\Requests\Inventory\InventoryRefillRequestStoreRequest;
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

        return view('barista.refill-requests.index', [
            'requests' => $this->requests->paginateForBarista($request->user('admin'), $filters),
            'ingredientOptions' => $this->inventory->ingredientOptions(),
            'statusOptions' => InventoryRefillRequestStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', InventoryRefillRequest::class);

        return view('barista.refill-requests.create', [
            'requestModel' => new InventoryRefillRequest,
            'ingredientOptions' => $this->inventory->ingredientOptions(activeOnly: true),
        ]);
    }

    public function store(InventoryRefillRequestStoreRequest $request): RedirectResponse
    {
        $refillRequest = $this->service->store(
            $request->user('admin'),
            $this->parser->getTransferFromArrayData($request->validated()),
        );

        return redirect()
            ->route('barista.refill-requests.show', $refillRequest)
            ->with('status', 'Refill request submitted successfully.');
    }

    public function show(InventoryRefillRequest $inventoryRefillRequest): View
    {
        $this->authorize('view', $inventoryRefillRequest);

        return view('barista.refill-requests.show', [
            'request' => $inventoryRefillRequest->load(['ingredient.brand', 'ingredient.category', 'requestedBy', 'reviewedBy']),
        ]);
    }
}
