<?php

namespace App\Http\Controllers\Barista;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recipe\RecipeIndexRequest;
use App\Models\Recipe;
use App\Parsers\Recipe\RecipeParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Recipe\RecipeRepositoryInterface;
use Illuminate\Contracts\View\View;

class RecipeController extends Controller
{
    public function __construct(
        protected RecipeParserInterface $parser,
        protected RecipeRepositoryInterface $recipes,
        protected ProductCategoryRepositoryInterface $categories,
    ) {}

    public function index(RecipeIndexRequest $request): View
    {
        $this->authorize('viewAny', Recipe::class);

        return view('barista.recipes.index', [
            'recipes' => $this->recipes->paginateForBarista($this->parser->getFilterTransferFromArrayData($request->validated())),
            'categoryOptions' => $this->categories->allOptions(),
            'productOptions' => $this->recipes->productOptions(),
            'ingredientOptions' => $this->recipes->activeIngredientOptions(),
        ]);
    }

    public function show(Recipe $recipe): View
    {
        $this->authorize('view', $recipe);

        return view('barista.recipes.show', [
            'recipe' => $recipe->load(['variant.product.category', 'lines.ingredient.brand']),
        ]);
    }
}
