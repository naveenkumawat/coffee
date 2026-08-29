<?php

namespace App\Http\Controllers\Barista;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductIndexRequest;
use App\Models\Product;
use App\Parsers\Product\ProductParserInterface;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductFlavourRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductParserInterface $parser,
        protected ProductRepositoryInterface $products,
        protected ProductCategoryRepositoryInterface $categories,
        protected ProductFlavourRepositoryInterface $flavours,
    ) {}

    public function index(ProductIndexRequest $request): View
    {
        $this->authorize('viewAny', Product::class);

        return view('barista.products.index', [
            'products' => $this->products->paginateForBarista($this->parser->getFilterTransferFromArrayData($request->validated())),
            'categoryOptions' => $this->categories->allOptions(),
            'flavourOptions' => $this->flavours->allOptions(),
        ]);
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        return view('barista.products.show', [
            'product' => $product->load(['category', 'flavours', 'variants.recipe']),
        ]);
    }
}
