<?php

namespace App\Listeners\Menu;

use App\Services\Menu\MenuCatalogServiceInterface;

class FlushMenuCatalogCache
{
    public function __construct(
        protected MenuCatalogServiceInterface $menuCatalogService,
    ) {}

    public function handle(object $event): void
    {
        $this->menuCatalogService->flushPublicCache();
    }
}
