<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuItem\MenuItemCreateRequest;
use App\Http\Requests\MenuItem\MenuItemUpdateRequest;
use App\Models\MenuItem;
use App\Parsers\Menu\MenuItemParserInterface;
use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Repositories\Menu\MenuItemRepositoryInterface;
use App\Services\Menu\MenuItemServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MenuItemController extends Controller
{
    public function __construct(
        protected MenuItemParserInterface $parser,
        protected MenuItemRepositoryInterface $items,
        protected MenuCategoryRepositoryInterface $categories,
        protected MenuItemServiceInterface $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MenuItem::class);

        return view('administrator.menu.items.index', [
            'items' => $this->items->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MenuItem::class);

        return view('administrator.menu.items.create', [
            'item' => new MenuItem(['is_available' => true, 'sort_order' => 10]),
            'categories' => $this->categories->activeOptions(),
        ]);
    }

    public function store(MenuItemCreateRequest $request): RedirectResponse
    {
        $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.menu.items.index')
            ->with('status', 'Menu item created successfully.');
    }

    public function show(MenuItem $menuItem): RedirectResponse
    {
        return redirect()->route('administrator.menu.items.edit', $menuItem);
    }

    public function edit(MenuItem $menuItem): View
    {
        $this->authorize('update', $menuItem);

        return view('administrator.menu.items.edit', [
            'item' => $menuItem,
            'categories' => $this->categories->allOptions(),
        ]);
    }

    public function update(MenuItemUpdateRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorize('update', $menuItem);
        $this->service->update($menuItem, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.menu.items.edit', $menuItem)
            ->with('status', 'Menu item updated successfully.');
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $this->authorize('delete', $menuItem);
        $this->service->delete($menuItem);

        return redirect()
            ->route('administrator.menu.items.index')
            ->with('status', 'Menu item removed successfully.');
    }
}
