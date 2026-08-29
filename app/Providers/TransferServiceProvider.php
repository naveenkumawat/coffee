<?php

namespace App\Providers;

use App\Transfers\Menu\MenuCategoryTransfer;
use App\Transfers\Menu\MenuCategoryTransferInterface;
use App\Transfers\Menu\MenuItemTransfer;
use App\Transfers\Menu\MenuItemTransferInterface;
use Illuminate\Support\ServiceProvider;

class TransferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MenuCategoryTransferInterface::class, MenuCategoryTransfer::class);
        $this->app->bind(MenuItemTransferInterface::class, MenuItemTransfer::class);
    }
}
