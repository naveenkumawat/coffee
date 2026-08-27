<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItem\StoreMenuItemRequest;
use App\Http\Requests\Admin\MenuItem\UpdateMenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Repositories\Menu\MenuItemRepository;
use App\Services\Menu\MenuCatalogService;
use App\Services\Menu\MenuItemService;
use App\Transfers\Menu\MenuItemData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MenuItemController extends Controller
{
    public function __construct(
        protected MenuItemRepository $items,
        protected MenuItemService $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MenuItem::class);

        return view('admin.menu.items.index', [
            'items' => $this->items->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MenuItem::class);

        return view('admin.menu.items.create', [
            'item' => new MenuItem(['is_available' => true, 'sort_order' => 10]),
            'categories' => MenuCategory::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        $this->service->store(MenuItemData::fromArray($request->validated()));

        return redirect()
            ->route('admin.menu.items.index')
            ->with('status', 'Menu item created successfully.');
    }

    public function show(MenuItem $menuItem): RedirectResponse
    {
        return redirect()->route('admin.menu.items.edit', $menuItem);
    }

    public function edit(MenuItem $menuItem): View
    {
        $this->authorize('update', $menuItem);

        return view('admin.menu.items.edit', [
            'item' => $menuItem,
            'categories' => MenuCategory::query()->orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorize('update', $menuItem);
        $this->service->update($menuItem, MenuItemData::fromArray($request->validated()));

        return redirect()
            ->route('admin.menu.items.edit', $menuItem)
            ->with('status', 'Menu item updated successfully.');
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $this->authorize('delete', $menuItem);
        $menuItem->delete();
        app(MenuCatalogService::class)->flushPublicCache();

        return redirect()
            ->route('admin.menu.items.index')
            ->with('status', 'Menu item removed successfully.');
    }
}
