<?php

namespace App\Services\Menu;

use App\Events\Menu\MenuItemSaved;
use App\Models\MenuItem;
use App\Transfers\Menu\MenuItemData;
use Illuminate\Support\Facades\DB;

class MenuItemService
{
    public function store(MenuItemData $data): MenuItem
    {
        return DB::transaction(function () use ($data): MenuItem {
            $item = MenuItem::query()->create($data->toArray());
            MenuItemSaved::dispatch($item->fresh('category'));

            return $item;
        });
    }

    public function update(MenuItem $menuItem, MenuItemData $data): MenuItem
    {
        return DB::transaction(function () use ($menuItem, $data): MenuItem {
            $menuItem->update($data->toArray());
            MenuItemSaved::dispatch($menuItem->fresh('category'));

            return $menuItem;
        });
    }
}
