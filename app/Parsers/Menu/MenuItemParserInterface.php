<?php

namespace App\Parsers\Menu;

use App\Models\MenuItem;
use App\Transfers\Menu\MenuItemTransferInterface;

interface MenuItemParserInterface
{
    public function getTransferFromModelEntity(MenuItem $menuItem): MenuItemTransferInterface;

    public function getTransferFromArrayData(array $menuItemData): MenuItemTransferInterface;
}
