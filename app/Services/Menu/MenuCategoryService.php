<?php

namespace App\Services\Menu;

use App\Events\Menu\MenuCategorySaved;
use App\Models\MenuCategory;
use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Transfers\Menu\MenuCategoryTransferInterface;
use Illuminate\Support\Facades\DB;

class MenuCategoryService implements MenuCategoryServiceInterface
{
    public function __construct(
        protected MenuCategoryRepositoryInterface $categories,
        protected MenuCatalogServiceInterface $menuCatalogService,
    ) {}

    public function store(MenuCategoryTransferInterface $data): MenuCategory
    {
        $category = DB::transaction(function () use ($data): MenuCategory {
            return $this->categories->create($data->toArray());
        });

        MenuCategorySaved::dispatch($category);

        return $category;
    }

    public function update(MenuCategory $menuCategory, MenuCategoryTransferInterface $data): MenuCategory
    {
        $menuCategory = DB::transaction(function () use ($menuCategory, $data): MenuCategory {
            return $this->categories->update($menuCategory, $data->toArray());
        });

        MenuCategorySaved::dispatch($menuCategory);

        return $menuCategory;
    }

    public function delete(MenuCategory $menuCategory): void
    {
        DB::transaction(function () use ($menuCategory): void {
            $this->categories->delete($menuCategory);
        });

        $this->menuCatalogService->flushPublicCache();
    }
}
