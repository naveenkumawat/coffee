<?php

namespace App\Http\Controllers\Barista;

use App\Enums\IngredientUnit;
use App\Enums\InventoryStockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryIndexRequest;
use App\Models\InventoryTransaction;
use App\Parsers\Inventory\InventoryParserInterface;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Repositories\Inventory\InventoryRepositoryInterface;
use Illuminate\Contracts\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryParserInterface $parser,
        protected InventoryRepositoryInterface $inventory,
        protected IngredientCategoryRepositoryInterface $categories,
        protected IngredientBrandRepositoryInterface $brands,
    ) {}

    public function index(InventoryIndexRequest $request): View
    {
        $this->authorize('viewAny', InventoryTransaction::class);

        $filters = $this->parser->getOverviewFilterTransferFromArrayData($request->validated());

        return view('barista.inventory.index', [
            'inventoryItems' => $this->inventory->paginateOverview($filters),
            'categoryOptions' => $this->categories->allOptions(),
            'brandOptions' => $this->brands->allOptions(),
            'unitOptions' => IngredientUnit::options(),
            'stockStatusOptions' => InventoryStockStatus::options(),
        ]);
    }
}
