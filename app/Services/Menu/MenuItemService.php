<?php

namespace App\Services\Menu;

use App\Events\Menu\MenuItemSaved;
use App\Models\MenuItem;
use App\Repositories\Menu\MenuItemRepositoryInterface;
use App\Transfers\Menu\MenuItemTransferInterface;
use Illuminate\Support\Facades\DB;

class MenuItemService implements MenuItemServiceInterface
{
    public function __construct(
        protected MenuItemRepositoryInterface $items,
        protected MenuCatalogServiceInterface $menuCatalogService,
    ) {}

    public function store(MenuItemTransferInterface $data): MenuItem
    {
        $menuItem = DB::transaction(function () use ($data): MenuItem {
            return $this->items->create($data->toArray());
        });

        MenuItemSaved::dispatch($menuItem->fresh('category'));

        return $menuItem;
    }

    public function update(MenuItem $menuItem, MenuItemTransferInterface $data): MenuItem
    {
        $menuItem = DB::transaction(function () use ($menuItem, $data): MenuItem {
            return $this->items->update($menuItem, $data->toArray());
        });

        MenuItemSaved::dispatch($menuItem->fresh('category'));

        return $menuItem;
    }

    public function delete(MenuItem $menuItem): void
    {
        DB::transaction(function () use ($menuItem): void {
            $this->items->delete($menuItem);
        });

        $this->menuCatalogService->flushPublicCache();
    }
}
