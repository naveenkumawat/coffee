<?php

namespace App\Services\Menu;

use Illuminate\Database\Eloquent\Collection;

interface MenuCatalogServiceInterface
{
    public function publicCatalog(): Collection;

    public function featuredItems(): Collection;

    public function flushPublicCache(): void;
}
