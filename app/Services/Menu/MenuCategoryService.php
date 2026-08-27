<?php

namespace App\Services\Menu;

use App\Events\Menu\MenuCategorySaved;
use App\Models\MenuCategory;
use App\Transfers\Menu\MenuCategoryData;
use Illuminate\Support\Facades\DB;

class MenuCategoryService
{
    public function store(MenuCategoryData $data): MenuCategory
    {
        return DB::transaction(function () use ($data): MenuCategory {
            $category = MenuCategory::query()->create($data->toArray());
            MenuCategorySaved::dispatch($category->fresh());

            return $category;
        });
    }

    public function update(MenuCategory $menuCategory, MenuCategoryData $data): MenuCategory
    {
        return DB::transaction(function () use ($menuCategory, $data): MenuCategory {
            $menuCategory->update($data->toArray());
            MenuCategorySaved::dispatch($menuCategory->fresh());

            return $menuCategory;
        });
    }
}
