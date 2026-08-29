<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\IngredientUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\RecipeCreateRequest;
use App\Http\Requests\Recipe\RecipeIndexRequest;
use App\Http\Requests\Recipe\RecipeUpdateRequest;
use App\Models\Recipe;
use App\Parsers\Recipe\RecipeParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Recipe\RecipeRepositoryInterface;
use App\Services\Recipe\RecipeCostingServiceInterface;
use App\Services\Recipe\RecipeServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecipeController extends Controller
{
    public function __construct(
        protected RecipeParserInterface $parser,
        protected RecipeRepositoryInterface $recipes,
        protected ProductCategoryRepositoryInterface $categories,
        protected RecipeCostingServiceInterface $costing,
        protected RecipeServiceInterface $service,
    ) {}

    public function index(RecipeIndexRequest $request): View
    {
        $this->authorize('viewAny', Recipe::class);

        $recipes = $this->recipes->paginateForAdmin($this->parser->getFilterTransferFromArrayData($request->validated()));

        return view('administrator.recipes.index', [
            'recipes' => $recipes,
            'categoryOptions' => $this->categories->allOptions(),
            'productOptions' => $this->recipes->productOptions(),
            'ingredientOptions' => $this->recipes->activeIngredientOptions(),
            'costingByRecipe' => collect($recipes->items())->mapWithKeys(fn (Recipe $recipe): array => [
                $recipe->getKey() => $this->costing->summarize($recipe),
            ])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Recipe::class);

        $variantId = filled($request->input('product_variant_id'))
            ? (int) $request->input('product_variant_id')
            : null;

        return view('administrator.recipes.create', [
            'recipe' => new Recipe(['is_active' => true]),
            'variantOptions' => $this->recipes->activeVariantOptions(),
            'ingredientOptions' => $this->recipes->activeIngredientOptions(),
            'unitOptions' => IngredientUnit::options(),
            'lineRows' => $this->defaultLineRows(),
            'selectedVariantId' => $variantId,
        ]);
    }

    public function store(RecipeCreateRequest $request): RedirectResponse
    {
        $recipe = $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.recipes.show', $recipe)
            ->with('status', 'Recipe created successfully.');
    }

    public function show(Recipe $recipe): View
    {
        $this->authorize('view', $recipe);

        $recipe->load(['variant.product.category', 'lines.ingredient.brand']);

        return view('administrator.recipes.show', [
            'recipe' => $recipe,
            'costing' => $this->costing->summarize($recipe),
        ]);
    }

    public function edit(Recipe $recipe): View
    {
        $this->authorize('update', $recipe);

        $recipe->load(['variant.product.category', 'lines.ingredient']);

        return view('administrator.recipes.edit', [
            'recipe' => $recipe,
            'variantOptions' => $this->variantOptionsForEdit($recipe),
            'ingredientOptions' => $this->ingredientOptionsForEdit($recipe),
            'unitOptions' => IngredientUnit::options(),
            'lineRows' => $recipe->lines,
            'selectedVariantId' => $recipe->product_variant_id,
            'costing' => $this->costing->summarize($recipe),
        ]);
    }

    public function update(RecipeUpdateRequest $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $this->service->update($recipe, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.recipes.show', $recipe)
            ->with('status', 'Recipe updated successfully.');
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('delete', $recipe);

        $this->service->delete($recipe);

        return redirect()
            ->route('administrator.recipes.index')
            ->with('status', 'Recipe archived successfully.');
    }

    protected function defaultLineRows(): array
    {
        return [
            ['ingredient_id' => null, 'quantity' => null, 'measurement_unit' => 'g', 'sort_order' => 1],
            ['ingredient_id' => null, 'quantity' => null, 'measurement_unit' => 'ml', 'sort_order' => 2],
            ['ingredient_id' => null, 'quantity' => null, 'measurement_unit' => 'piece', 'sort_order' => 3],
        ];
    }

    protected function variantOptionsForEdit(Recipe $recipe): array
    {
        $options = $this->recipes->activeVariantOptions();
        $variant = $recipe->variant;

        if ($variant && ! array_key_exists($variant->getKey(), $options)) {
            $options[$variant->getKey()] = sprintf('%s - %s (Inactive)', $variant->product?->name ?? 'Product', $variant->name);
            asort($options);
        }

        return $options;
    }

    protected function ingredientOptionsForEdit(Recipe $recipe): array
    {
        $options = $this->recipes->activeIngredientOptions();

        foreach ($recipe->lines as $line) {
            if ($line->ingredient && ! array_key_exists($line->ingredient->getKey(), $options)) {
                $options[$line->ingredient->getKey()] = sprintf('%s (Inactive)', $line->ingredient->name);
            }
        }

        asort($options);

        return $options;
    }
}
