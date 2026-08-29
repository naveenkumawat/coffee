<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngredientBrand\IngredientBrandCreateRequest;
use App\Http\Requests\IngredientBrand\IngredientBrandIndexRequest;
use App\Http\Requests\IngredientBrand\IngredientBrandUpdateRequest;
use App\Models\IngredientBrand;
use App\Parsers\Ingredient\IngredientBrandParserInterface;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Repositories\Ingredient\IngredientRepositoryInterface;
use App\Services\Ingredient\IngredientBrandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class IngredientBrandController extends Controller
{
    public function __construct(
        protected IngredientBrandParserInterface $parser,
        protected IngredientBrandRepositoryInterface $brands,
        protected IngredientRepositoryInterface $ingredients,
        protected IngredientBrandServiceInterface $service,
    ) {}

    public function index(IngredientBrandIndexRequest $request): View
    {
        $this->authorize('viewAny', IngredientBrand::class);

        return view('administrator.ingredients.brands.index', [
            'brands' => $this->brands->paginateForAdmin($this->parser->getFilterTransferFromArrayData($request->validated())),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', IngredientBrand::class);

        return view('administrator.ingredients.brands.create', [
            'brand' => new IngredientBrand(['is_active' => true]),
        ]);
    }

    public function store(IngredientBrandCreateRequest $request): RedirectResponse
    {
        $brand = $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.ingredients.brands.edit', $brand)
            ->with('status', 'Ingredient brand created successfully.');
    }

    public function show(IngredientBrand $ingredientBrand): View
    {
        $this->authorize('view', $ingredientBrand);

        return view('administrator.ingredients.brands.show', [
            'brand' => $ingredientBrand,
            'ingredients' => $this->ingredients->paginateForBrand($ingredientBrand),
        ]);
    }

    public function edit(IngredientBrand $ingredientBrand): View
    {
        $this->authorize('update', $ingredientBrand);

        return view('administrator.ingredients.brands.edit', [
            'brand' => $ingredientBrand,
        ]);
    }

    public function update(IngredientBrandUpdateRequest $request, IngredientBrand $ingredientBrand): RedirectResponse
    {
        $this->authorize('update', $ingredientBrand);

        $this->service->update($ingredientBrand, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.ingredients.brands.edit', $ingredientBrand)
            ->with('status', 'Ingredient brand updated successfully.');
    }

    public function destroy(IngredientBrand $ingredientBrand): RedirectResponse
    {
        $this->authorize('delete', $ingredientBrand);

        $this->service->delete($ingredientBrand);

        return redirect()
            ->route('administrator.ingredients.brands.index')
            ->with('status', 'Ingredient brand archived successfully.');
    }
}
