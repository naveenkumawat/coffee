<?php

namespace App\Services\Menu;

use App\Models\MenuCategory;
use App\Transfers\Menu\MenuCategoryTransferInterface;

interface MenuCategoryServiceInterface
{
    public function store(MenuCategoryTransferInterface $data): MenuCategory;

    public function update(MenuCategory $menuCategory, MenuCategoryTransferInterface $data): MenuCategory;

    public function delete(MenuCategory $menuCategory): void;
}
