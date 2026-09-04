<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductCategory\ProductCategoryCreateRequest;
use App\Http\Requests\ProductCategory\ProductCategoryIndexRequest;
use App\Http\Requests\ProductCategory\ProductCategoryUpdateRequest;
use App\Models\ProductCategory;
use App\Parsers\Product\ProductCategoryParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\Product\ProductCategoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductCategoryController extends Controller
{
    public function __construct(
        protected ProductCategoryParserInterface $parser,
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductRepositoryInterface $products,
        protected ProductCategoryServiceInterface $service,
    ) {}

    public function index(ProductCategoryIndexRequest $request): View
    {
        $this->authorize('viewAny', ProductCategory::class);

        return view('administrator.products.categories.index', [
            'categories' => $this->categories->paginateForAdmin($this->parser->getFilterTransferFromArrayData($request->validated())),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ProductCategory::class);

        return view('administrator.products.categories.create', [
            'category' => new ProductCategory(['is_active' => true, 'sort_order' => 10]),
        ]);
    }

    public function store(ProductCategoryCreateRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except(['image', 'remove_image']);
        $category = $this->service->store($this->parser->getTransferFromArrayData($payload));
        $this->service->syncImage($category, $request->file('image'), $request->boolean('remove_image'));

        return redirect()
            ->route('administrator.products.categories.edit', $category)
            ->with('status', 'Product category created successfully.');
    }

    public function show(ProductCategory $productCategory): View
    {
        $this->authorize('view', $productCategory);

        return view('administrator.products.categories.show', [
            'category' => $productCategory,
            'products' => $this->products->paginateForCategory($productCategory),
        ]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        $this->authorize('update', $productCategory);

        return view('administrator.products.categories.edit', [
            'category' => $productCategory,
        ]);
    }

    public function update(ProductCategoryUpdateRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('update', $productCategory);

        $payload = $request->safe()->except(['image', 'remove_image']);
        $productCategory = $this->service->update($productCategory, $this->parser->getTransferFromArrayData($payload));
        $this->service->syncImage($productCategory, $request->file('image'), $request->boolean('remove_image'));

        return redirect()
            ->route('administrator.products.categories.edit', $productCategory)
            ->with('status', 'Product category updated successfully.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $this->authorize('delete', $productCategory);

        $this->service->delete($productCategory);

        return redirect()
            ->route('administrator.products.categories.index')
            ->with('status', 'Product category archived successfully.');
    }
}
