<?php

namespace App\Http\Controllers;

use App\Services\Menu\MenuCatalogService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(MenuCatalogService $menuCatalogService): View
    {
        return view('home', [
            'featuredItems' => $menuCatalogService->featuredItems(),
            'categories' => $menuCatalogService->publicCatalog(),
        ]);
    }
}
