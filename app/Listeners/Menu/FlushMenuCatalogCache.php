<?php

namespace App\Listeners\Menu;

use App\Services\Menu\MenuCatalogService;

class FlushMenuCatalogCache
{
    public function __construct(protected MenuCatalogService $menuCatalogService) {}

    public function handle(object $event): void
    {
        $this->menuCatalogService->flushPublicCache();
    }
}
