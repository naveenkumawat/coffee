<?php

namespace App\Observers;

use App\Services\Product\ProductCatalogServiceInterface;

class PublicCatalogCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function saved(mixed $model): void
    {
        $this->catalog->flushPublicCache();
    }

    public function deleted(mixed $model): void
    {
        $this->catalog->flushPublicCache();
    }

    public function restored(mixed $model): void
    {
        $this->catalog->flushPublicCache();
    }
}
