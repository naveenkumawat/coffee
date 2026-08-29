<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Repositories\Menu\MenuItemRepositoryInterface;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        MenuCategoryRepositoryInterface $categoryRepository,
        MenuItemRepositoryInterface $itemRepository,
    ): View {
        $categoryPage = $categoryRepository->paginateForAdmin(5);
        $itemPage = $itemRepository->paginateForAdmin(5);

        return view('administrator.dashboard.index', [
            'categoryCount' => $categoryPage->total(),
            'itemCount' => $itemPage->total(),
            'latestCategories' => $categoryPage->items(),
            'latestItems' => $itemPage->items(),
        ]);
    }
}
