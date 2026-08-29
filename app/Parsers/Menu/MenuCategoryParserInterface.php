<?php

namespace App\Parsers\Menu;

use App\Models\MenuCategory;
use App\Transfers\Menu\MenuCategoryTransferInterface;

interface MenuCategoryParserInterface
{
    public function getTransferFromModelEntity(MenuCategory $menuCategory): MenuCategoryTransferInterface;

    public function getTransferFromArrayData(array $menuCategoryData): MenuCategoryTransferInterface;
}
