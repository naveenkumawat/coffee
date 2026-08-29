<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngredientCategory\IngredientCategoryCreateRequest;
use App\Http\Requests\IngredientCategory\IngredientCategoryUpdateRequest;
use App\Models\IngredientCategory;
use App\Parsers\Ingredient\IngredientCategoryParserInterface;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Repositories\Ingredient\IngredientRepositoryInterface;
use App\Services\Ingredient\IngredientCategoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class IngredientCategoryController extends Controller
{
    public function __construct(
        protected IngredientCategoryParserInterface $parser,
        protected IngredientCategoryRepositoryInterface $categories,
        protected IngredientRepositoryInterface $ingredients,
        protected IngredientCategoryServiceInterface $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', IngredientCategory::class);

        return view('administrator.ingredients.categories.index', [
            'categories' => $this->categories->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', IngredientCategory::class);

        return view('administrator.ingredients.categories.create', [
            'category' => new IngredientCategory(['is_active' => true]),
        ]);
    }

    public function store(IngredientCategoryCreateRequest $request): RedirectResponse
    {
        $category = $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.ingredients.categories.edit', $category)
            ->with('status', 'Ingredient category created successfully.');
    }

    public function show(IngredientCategory $ingredientCategory): View
    {
        $this->authorize('view', $ingredientCategory);

        return view('administrator.ingredients.categories.show', [
            'category' => $ingredientCategory,
            'ingredients' => $this->ingredients->paginateForCategory($ingredientCategory),
        ]);
    }

    public function edit(IngredientCategory $ingredientCategory): View
    {
        $this->authorize('update', $ingredientCategory);

        return view('administrator.ingredients.categories.edit', [
            'category' => $ingredientCategory,
        ]);
    }

    public function update(IngredientCategoryUpdateRequest $request, IngredientCategory $ingredientCategory): RedirectResponse
    {
        $this->authorize('update', $ingredientCategory);

        $this->service->update($ingredientCategory, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.ingredients.categories.edit', $ingredientCategory)
            ->with('status', 'Ingredient category updated successfully.');
    }

    public function destroy(IngredientCategory $ingredientCategory): RedirectResponse
    {
        $this->authorize('delete', $ingredientCategory);

        $this->service->delete($ingredientCategory);

        return redirect()
            ->route('administrator.ingredients.categories.index')
            ->with('status', 'Ingredient category archived successfully.');
    }
}
