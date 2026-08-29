<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\IngredientUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ingredient\IngredientCreateRequest;
use App\Http\Requests\Ingredient\IngredientIndexRequest;
use App\Http\Requests\Ingredient\IngredientUpdateRequest;
use App\Models\Ingredient;
use App\Parsers\Ingredient\IngredientParserInterface;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Repositories\Ingredient\IngredientRepositoryInterface;
use App\Services\Ingredient\IngredientServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class IngredientController extends Controller
{
    public function __construct(
        protected IngredientParserInterface $parser,
        protected IngredientRepositoryInterface $ingredients,
        protected IngredientCategoryRepositoryInterface $categories,
        protected IngredientBrandRepositoryInterface $brands,
        protected IngredientServiceInterface $service,
    ) {}

    public function index(IngredientIndexRequest $request): View
    {
        $this->authorize('viewAny', Ingredient::class);

        $filters = $this->parser->getFilterTransferFromArrayData($request->validated());

        return view('administrator.ingredients.index', [
            'ingredients' => $this->ingredients->paginateForAdmin($filters),
            'categoryOptions' => $this->categories->allOptions(),
            'brandOptions' => $this->brands->allOptions(),
            'unitOptions' => IngredientUnit::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Ingredient::class);

        return view('administrator.ingredients.create', [
            'ingredient' => new Ingredient([
                'is_active' => true,
                'purchase_quantity' => '1.000',
                'purchase_cost' => '0.00',
                'current_stock' => '0.000',
                'minimum_stock' => '0.000',
                'reorder_level' => '0.000',
            ]),
            'categoryOptions' => $this->categories->activeOptions(),
            'brandOptions' => $this->brands->activeOptions(),
            'unitOptions' => IngredientUnit::options(),
        ]);
    }

    public function store(IngredientCreateRequest $request): RedirectResponse
    {
        $ingredient = $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.ingredients.edit', $ingredient)
            ->with('status', 'Ingredient created successfully.');
    }

    public function show(Ingredient $ingredient): View
    {
        $this->authorize('view', $ingredient);

        return view('administrator.ingredients.show', [
            'ingredient' => $ingredient->load(['brand', 'category']),
        ]);
    }

    public function edit(Ingredient $ingredient): View
    {
        $this->authorize('update', $ingredient);

        return view('administrator.ingredients.edit', [
            'ingredient' => $ingredient,
            'categoryOptions' => $this->categories->allOptions(),
            'brandOptions' => $this->brandOptionsForEdit($ingredient),
            'unitOptions' => IngredientUnit::options(),
        ]);
    }

    public function update(IngredientUpdateRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $this->service->update($ingredient, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.ingredients.edit', $ingredient)
            ->with('status', 'Ingredient updated successfully.');
    }

    public function destroy(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('delete', $ingredient);

        $this->service->delete($ingredient);

        return redirect()
            ->route('administrator.ingredients.index')
            ->with('status', 'Ingredient archived successfully.');
    }

    protected function brandOptionsForEdit(Ingredient $ingredient): array
    {
        $options = $this->brands->activeOptions();

        if ($ingredient->ingredient_brand_id && ! array_key_exists($ingredient->ingredient_brand_id, $options) && $ingredient->brand) {
            $options[$ingredient->ingredient_brand_id] = sprintf('%s (Inactive)', $ingredient->brand->name);
            asort($options);
        }

        return $options;
    }
}
