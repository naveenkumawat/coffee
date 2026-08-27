<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\Menu\MenuCategoryRepository;
use App\Repositories\Menu\MenuItemRepository;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        MenuCategoryRepository $categoryRepository,
        MenuItemRepository $itemRepository,
    ): View {
        $categoryPage = $categoryRepository->paginateForAdmin(5);
        $itemPage = $itemRepository->paginateForAdmin(5);

        return view('admin.dashboard.index', [
            'categoryCount' => $categoryPage->total(),
            'itemCount' => $itemPage->total(),
            'latestCategories' => $categoryPage->items(),
            'latestItems' => $itemPage->items(),
        ]);
    }
}
