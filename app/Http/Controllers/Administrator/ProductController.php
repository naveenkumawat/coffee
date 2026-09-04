<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\IngredientUnit;
use App\Enums\ProductServingUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductCreateRequest;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Models\AddOn;
use App\Models\Ingredient;
use App\Models\Product;
use App\Parsers\Product\ProductParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Repositories\Product\ProductTagRepositoryInterface;
use App\Services\Product\ProductReadinessServiceInterface;
use App\Services\Product\ProductServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function __construct(
        protected ProductParserInterface $parser,
        protected ProductRepositoryInterface $products,
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductFlavourRepositoryInterface $flavours,
        protected ProductTagRepositoryInterface $tags,
        protected ProductServiceInterface $service,
        protected ProductReadinessServiceInterface $readiness,
    ) {}

    public function index(ProductIndexRequest $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->products->paginateForAdmin($this->parser->getFilterTransferFromArrayData($request->validated()));
        $reports = $this->readiness->evaluateMany($products->getCollection());

        return view('administrator.products.index', [
            'products' => $products,
            'readinessReports' => $reports,
            'categoryOptions' => $this->categories->allOptions(),
            'flavourOptions' => $this->flavours->allOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('administrator.products.create', [
            'product' => new Product([
                'is_active' => false,
                'is_available' => true,
                'is_vegetarian' => false,
                'is_customizable' => false,
                'sort_order' => 10,
            ]),
            'categoryOptions' => $this->categories->activeOptions(),
            'flavourOptions' => $this->flavours->activeOptions(),
            'tagOptions' => $this->tags->activeOptions(),
            'variantUnitOptions' => ProductServingUnit::options(),
            'variantRows' => $this->defaultVariantRows(),
            'addOnOptions' => $this->addOnOptions(),
            'ingredientOptions' => $this->ingredientOptions(),
            'ingredientUnitOptions' => IngredientUnit::options(),
            'addOnRows' => [],
        ]);
    }

    public function store(ProductCreateRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except(['image', 'remove_image']);
        $product = $this->service->store($this->parser->getTransferFromArrayData($payload));
        $this->service->syncAddOnAssignments($product, $payload['add_ons'] ?? []);
        $this->service->syncImage(
            $product,
            $request->file('image'),
            $request->boolean('remove_image'),
        );
        $this->service->assertActiveProductIsLaunchReady($product->fresh());

        return redirect()
            ->route('administrator.products.edit', $product)
            ->with('status', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['category', 'flavours', 'tags', 'variants.recipe.lines.ingredient', 'productAddOns.recipeLines.ingredient', 'productAddOns.addOn']);

        return view('administrator.products.show', [
            'product' => $product,
            'readiness' => $this->readiness->evaluate($product),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['category', 'flavours', 'tags', 'variants', 'productAddOns.recipeLines', 'productAddOns.addOn']);

        return view('administrator.products.edit', [
            'product' => $product,
            'categoryOptions' => $this->categoryOptionsForEdit($product),
            'flavourOptions' => $this->flavourOptionsForEdit($product),
            'tagOptions' => $this->tagOptionsForEdit($product),
            'variantUnitOptions' => ProductServingUnit::options(),
            'variantRows' => $product->variants->isNotEmpty() ? $product->variants : $this->defaultVariantRows(),
            'addOnOptions' => $this->addOnOptions(),
            'ingredientOptions' => $this->ingredientOptions(),
            'ingredientUnitOptions' => IngredientUnit::options(),
            'addOnRows' => $product->productAddOns->map(fn ($assignment): array => [
                'add_on_id' => $assignment->add_on_id,
                'price_override' => $assignment->price_override !== null ? (string) $assignment->price_override : '',
                'max_quantity' => $assignment->max_quantity,
                'sort_order' => $assignment->sort_order,
                'is_active' => (bool) $assignment->is_active,
                'lines' => $assignment->recipeLines->map(fn ($line): array => [
                    'id' => $line->id,
                    'ingredient_id' => $line->ingredient_id,
                    'quantity' => (string) $line->quantity,
                    'measurement_unit' => $line->measurement_unit instanceof \BackedEnum
                        ? $line->measurement_unit->value
                        : (string) $line->measurement_unit,
                    'sort_order' => $line->sort_order,
                ])->all(),
            ])->all(),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $payload = $request->safe()->except(['image', 'remove_image']);
        $product = $this->service->update($product, $this->parser->getTransferFromArrayData($payload));
        $this->service->syncAddOnAssignments($product, $payload['add_ons'] ?? []);
        $this->service->syncImage(
            $product,
            $request->file('image'),
            $request->boolean('remove_image'),
        );
        $this->service->assertActiveProductIsLaunchReady($product->fresh());

        return redirect()
            ->route('administrator.products.edit', $product)
            ->with('status', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->service->delete($product);

        return redirect()
            ->route('administrator.products.index')
            ->with('status', 'Product archived successfully.');
    }

    protected function categoryOptionsForEdit(Product $product): array
    {
        $options = $this->categories->activeOptions();

        if ($product->product_category_id && ! array_key_exists($product->product_category_id, $options) && $product->category) {
            $options[$product->product_category_id] = sprintf('%s (Inactive)', $product->category->name);
            asort($options);
        }

        return $options;
    }

    protected function flavourOptionsForEdit(Product $product): array
    {
        $options = $this->flavours->activeOptions();

        foreach ($product->flavours as $flavour) {
            if (! array_key_exists($flavour->getKey(), $options)) {
                $options[$flavour->getKey()] = sprintf('%s (Inactive)', $flavour->name);
            }
        }

        asort($options);

        return $options;
    }

    protected function tagOptionsForEdit(Product $product): array
    {
        $options = $this->tags->activeOptions();

        foreach ($product->tags as $tag) {
            if (! array_key_exists($tag->getKey(), $options)) {
                $options[$tag->getKey()] = sprintf('%s (Inactive)', $tag->name);
            }
        }

        asort($options);

        return $options;
    }

    /**
     * @return array<int, string>
     */
    protected function addOnOptions(): array
    {
        return AddOn::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function ingredientOptions(): array
    {
        return Ingredient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function defaultVariantRows(): array
    {
        return [
            [
                'name' => 'Regular',
                'serving_size_value' => '250.000',
                'serving_size_unit' => ProductServingUnit::Milliliter,
                'price' => '0.00',
                'sort_order' => 1,
                'is_active' => true,
                'is_available' => true,
            ],
        ];
    }
}
