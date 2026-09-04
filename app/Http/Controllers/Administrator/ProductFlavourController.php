<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductFlavour\ProductFlavourCreateRequest;
use App\Http\Requests\ProductFlavour\ProductFlavourIndexRequest;
use App\Http\Requests\ProductFlavour\ProductFlavourUpdateRequest;
use App\Models\ProductFlavour;
use App\Parsers\Product\ProductFlavourParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\Product\ProductFlavourServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductFlavourController extends Controller
{
    public function __construct(
        protected ProductFlavourParserInterface $parser,
        protected ProductFlavourRepositoryInterface $flavours,
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductRepositoryInterface $products,
        protected ProductFlavourServiceInterface $service,
    ) {}

    public function index(ProductFlavourIndexRequest $request): View
    {
        $this->authorize('viewAny', ProductFlavour::class);

        return view('administrator.products.flavours.index', [
            'flavours' => $this->flavours->paginateForAdmin($this->parser->getFilterTransferFromArrayData($request->validated())),
            'categoryOptions' => $this->categories->allOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ProductFlavour::class);

        return view('administrator.products.flavours.create', [
            'flavour' => new ProductFlavour(['is_active' => true]),
            'categoryOptions' => $this->categories->activeOptions(),
        ]);
    }

    public function store(ProductFlavourCreateRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except(['image', 'remove_image']);
        $flavour = $this->service->store($this->parser->getTransferFromArrayData($payload));
        $this->service->syncImage($flavour, $request->file('image'), $request->boolean('remove_image'));

        return redirect()
            ->route('administrator.products.flavours.edit', $flavour)
            ->with('status', 'Product flavour created successfully.');
    }

    public function show(ProductFlavour $productFlavour): View
    {
        $this->authorize('view', $productFlavour);

        return view('administrator.products.flavours.show', [
            'flavour' => $productFlavour->load('categories'),
            'products' => $this->products->paginateForFlavour($productFlavour),
        ]);
    }

    public function edit(ProductFlavour $productFlavour): View
    {
        $this->authorize('update', $productFlavour);

        return view('administrator.products.flavours.edit', [
            'flavour' => $productFlavour->load('categories'),
            'categoryOptions' => $this->categoryOptionsForEdit($productFlavour),
        ]);
    }

    public function update(ProductFlavourUpdateRequest $request, ProductFlavour $productFlavour): RedirectResponse
    {
        $this->authorize('update', $productFlavour);

        $payload = $request->safe()->except(['image', 'remove_image']);
        $productFlavour = $this->service->update($productFlavour, $this->parser->getTransferFromArrayData($payload));
        $this->service->syncImage($productFlavour, $request->file('image'), $request->boolean('remove_image'));

        return redirect()
            ->route('administrator.products.flavours.edit', $productFlavour)
            ->with('status', 'Product flavour updated successfully.');
    }

    public function destroy(ProductFlavour $productFlavour): RedirectResponse
    {
        $this->authorize('delete', $productFlavour);

        $this->service->delete($productFlavour);

        return redirect()
            ->route('administrator.products.flavours.index')
            ->with('status', 'Product flavour archived successfully.');
    }

    protected function categoryOptionsForEdit(ProductFlavour $productFlavour): array
    {
        $options = $this->categories->activeOptions();

        foreach ($productFlavour->categories as $category) {
            if (! array_key_exists($category->getKey(), $options)) {
                $options[$category->getKey()] = sprintf('%s (Inactive)', $category->name);
            }
        }

        asort($options);

        return $options;
    }
}
