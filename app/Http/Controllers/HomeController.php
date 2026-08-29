<?php

namespace App\Http\Controllers;

use App\Services\Product\ProductCatalogServiceInterface;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(ProductCatalogServiceInterface $productCatalogService): View
    {
        return view('home', [
            'featuredProducts' => $productCatalogService->featuredProducts(),
            'categories' => $productCatalogService->publicCatalog(),
        ]);
    }
}
