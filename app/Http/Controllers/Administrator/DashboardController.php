<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Repositories\Product\ProductCategoryRepositoryInterface;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Transfers\Product\ProductCategoryFilterTransferInterface;
use App\Transfers\Product\ProductFilterTransferInterface;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        ProductCategoryRepositoryInterface $categoryRepository,
        ProductRepositoryInterface $productRepository,
        ProductCategoryFilterTransferInterface $categoryFilters,
        ProductFilterTransferInterface $productFilters,
    ): View {
        $categoryPage = $categoryRepository->paginateForAdmin($categoryFilters, 5);
        $productPage = $productRepository->paginateForAdmin($productFilters, 5);

        return view('administrator.dashboard.index', [
            'categoryCount' => $categoryPage->total(),
            'itemCount' => $productPage->total(),
            'latestCategories' => $categoryPage->items(),
            'latestItems' => $productPage->items(),
        ]);
    }
}
