<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuCategory\StoreMenuCategoryRequest;
use App\Http\Requests\Admin\MenuCategory\UpdateMenuCategoryRequest;
use App\Models\MenuCategory;
use App\Repositories\Menu\MenuCategoryRepository;
use App\Services\Menu\MenuCatalogService;
use App\Services\Menu\MenuCategoryService;
use App\Transfers\Menu\MenuCategoryData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MenuCategoryController extends Controller
{
    public function __construct(
        protected MenuCategoryRepository $categories,
        protected MenuCategoryService $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MenuCategory::class);

        return view('admin.menu.categories.index', [
            'categories' => $this->categories->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MenuCategory::class);

        return view('admin.menu.categories.create', [
            'category' => new MenuCategory(['is_active' => true, 'sort_order' => 10]),
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): RedirectResponse
    {
        $this->service->store(MenuCategoryData::fromArray($request->validated()));

        return redirect()
            ->route('admin.menu.categories.index')
            ->with('status', 'Menu category created successfully.');
    }

    public function show(MenuCategory $menuCategory): RedirectResponse
    {
        return redirect()->route('admin.menu.categories.edit', $menuCategory);
    }

    public function edit(MenuCategory $menuCategory): View
    {
        $this->authorize('update', $menuCategory);

        return view('admin.menu.categories.edit', [
            'category' => $menuCategory,
        ]);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $menuCategory): RedirectResponse
    {
        $this->authorize('update', $menuCategory);
        $this->service->update($menuCategory, MenuCategoryData::fromArray($request->validated()));

        return redirect()
            ->route('admin.menu.categories.edit', $menuCategory)
            ->with('status', 'Menu category updated successfully.');
    }

    public function destroy(MenuCategory $menuCategory): RedirectResponse
    {
        $this->authorize('delete', $menuCategory);
        $menuCategory->delete();
        app(MenuCatalogService::class)->flushPublicCache();

        return redirect()
            ->route('admin.menu.categories.index')
            ->with('status', 'Menu category removed successfully.');
    }
}
