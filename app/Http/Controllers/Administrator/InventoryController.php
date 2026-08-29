<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\IngredientUnit;
use App\Enums\InventoryStockStatus;
use App\Enums\InventoryTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryHistoryIndexRequest;
use App\Http\Requests\Inventory\InventoryIndexRequest;
use App\Http\Requests\Inventory\InventoryMovementCreateRequest;
use App\Http\Requests\Inventory\InventoryMovementStoreRequest;
use App\Models\InventoryTransaction;
use App\Parsers\Inventory\InventoryParserInterface;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Repositories\Inventory\InventoryRefillRequestRepositoryInterface;
use App\Repositories\Inventory\InventoryRepositoryInterface;
use App\Services\Inventory\InventoryRefillRequestServiceInterface;
use App\Services\Inventory\InventoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryParserInterface $parser,
        protected InventoryRepositoryInterface $inventory,
        protected InventoryRefillRequestRepositoryInterface $refillRequests,
        protected IngredientCategoryRepositoryInterface $categories,
        protected IngredientBrandRepositoryInterface $brands,
        protected InventoryRefillRequestServiceInterface $refillRequestService,
        protected InventoryServiceInterface $service,
    ) {}

    public function index(InventoryIndexRequest $request): View
    {
        $this->authorize('viewAny', InventoryTransaction::class);

        $filters = $this->parser->getOverviewFilterTransferFromArrayData($request->validated());

        return view('administrator.inventory.index', [
            'inventoryItems' => $this->inventory->paginateOverview($filters),
            'categoryOptions' => $this->categories->allOptions(),
            'brandOptions' => $this->brands->allOptions(),
            'unitOptions' => IngredientUnit::options(),
            'stockStatusOptions' => InventoryStockStatus::options(),
            'pendingRefillCount' => $this->refillRequests->countPending(),
        ]);
    }

    public function history(InventoryHistoryIndexRequest $request): View
    {
        $this->authorize('viewAny', InventoryTransaction::class);

        $filters = $this->parser->getHistoryFilterTransferFromArrayData($request->validated());

        return view('administrator.inventory.history', [
            'transactions' => $this->inventory->paginateHistory($filters),
            'ingredientOptions' => $this->inventory->ingredientOptions(),
            'categoryOptions' => $this->categories->allOptions(),
            'brandOptions' => $this->brands->allOptions(),
            'transactionTypeOptions' => InventoryTransactionType::historyOptions(),
            'userOptions' => $this->inventory->transactionUserOptions(),
        ]);
    }

    public function createMovement(InventoryMovementCreateRequest $request): View
    {
        $this->authorize('create', InventoryTransaction::class);

        $validated = $request->validated();
        $ingredient = filled($validated['ingredient_id'] ?? null)
            ? $this->inventory->findIngredient((int) $validated['ingredient_id'])
            : null;

        return view('administrator.inventory.movements.create', [
            'ingredient' => $ingredient,
            'ingredientOptions' => $this->inventory->ingredientOptions(activeOnly: true),
            'transactionTypeOptions' => InventoryTransactionType::mutationOptions(),
            'unitOptions' => $ingredient
                ? $this->service->compatibleMeasurementUnitOptions($ingredient)
                : IngredientUnit::options(),
            'approvedRefillRequestOptions' => $ingredient
                ? $this->refillRequestService->approvedOptionsForIngredient($ingredient->getKey())
                : [],
            'selectedRefillRequestId' => filled($validated['inventory_refill_request_id'] ?? null)
                ? (int) $validated['inventory_refill_request_id']
                : null,
        ]);
    }

    public function storeMovement(InventoryMovementStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryTransaction::class);

        $data = $request->validated();
        $data['created_by'] = $request->user('admin')?->getAuthIdentifier();

        if (filled($data['inventory_refill_request_id'] ?? null)) {
            $data['reference_type'] = 'inventory_refill_request';
            $data['reference_id'] = (int) $data['inventory_refill_request_id'];
        }

        $transaction = $this->service->recordTransaction(
            $this->parser->getTransactionTransferFromArrayData($data),
        );

        return redirect()
            ->route('administrator.inventory.history', ['ingredient_id' => $transaction->ingredient_id])
            ->with('status', 'Inventory movement recorded successfully.');
    }
}
