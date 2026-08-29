<?php

namespace App\Services\Product;

use Illuminate\Database\Eloquent\Collection;

interface ProductCatalogServiceInterface
{
    public function publicCatalog(): Collection;

    public function featuredProducts(): Collection;

    public function flushPublicCache(): void;
}
