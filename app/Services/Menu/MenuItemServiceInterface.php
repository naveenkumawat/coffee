<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use App\Transfers\Menu\MenuItemTransferInterface;

interface MenuItemServiceInterface
{
    public function store(MenuItemTransferInterface $data): MenuItem;

    public function update(MenuItem $menuItem, MenuItemTransferInterface $data): MenuItem;

    public function delete(MenuItem $menuItem): void;
}
