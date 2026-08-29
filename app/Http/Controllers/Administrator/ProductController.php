<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ProductServingUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductCreateRequest;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Http\Requests\Product\ProductUpdateRequest;
use App\Models\Product;
use App\Parsers\Product\ProductParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
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
        protected ProductServiceInterface $service,
    ) {}

    public function index(ProductIndexRequest $request): View
    {
        $this->authorize('viewAny', Product::class);

        return view('administrator.products.index', [
            'products' => $this->products->paginateForAdmin($this->parser->getFilterTransferFromArrayData($request->validated())),
            'categoryOptions' => $this->categories->allOptions(),
            'flavourOptions' => $this->flavours->allOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('administrator.products.create', [
            'product' => new Product([
                'is_active' => true,
                'is_available' => true,
                'is_featured' => false,
                'sort_order' => 10,
            ]),
            'categoryOptions' => $this->categories->activeOptions(),
            'flavourOptions' => $this->flavours->activeOptions(),
            'variantUnitOptions' => ProductServingUnit::options(),
            'variantRows' => $this->defaultVariantRows(),
        ]);
    }

    public function store(ProductCreateRequest $request): RedirectResponse
    {
        $product = $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.products.edit', $product)
            ->with('status', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        return view('administrator.products.show', [
            'product' => $product->load(['category', 'flavours', 'variants.recipe']),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('administrator.products.edit', [
            'product' => $product->load(['category', 'flavours', 'variants']),
            'categoryOptions' => $this->categoryOptionsForEdit($product),
            'flavourOptions' => $this->flavourOptionsForEdit($product),
            'variantUnitOptions' => ProductServingUnit::options(),
            'variantRows' => $product->variants->isNotEmpty() ? $product->variants : $this->defaultVariantRows(),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->service->update($product, $this->parser->getTransferFromArrayData($request->validated()));

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
