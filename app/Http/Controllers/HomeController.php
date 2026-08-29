<?php

namespace App\Http\Controllers;

use App\Services\Menu\MenuCatalogServiceInterface;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(MenuCatalogServiceInterface $menuCatalogService): View
    {
        return view('home', [
            'featuredItems' => $menuCatalogService->featuredItems(),
            'categories' => $menuCatalogService->publicCatalog(),
        ]);
    }
}
