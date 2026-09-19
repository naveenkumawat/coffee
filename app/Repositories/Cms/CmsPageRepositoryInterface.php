<?php

namespace App\Repositories\Cms;

use App\Enums\CmsPageKey;
use App\Models\CmsPage;
use Illuminate\Support\Collection;

interface CmsPageRepositoryInterface
{
    /**
     * @return Collection<int, CmsPage>
     */
    public function allForAdmin(): Collection;

    public function findByKey(CmsPageKey $key): ?CmsPage;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function savePage(CmsPage $page, array $attributes): CmsPage;

    /**
     * @param  list<array{id?: int, question: string, answer: string, sort_order: int, is_active: bool}>  $items
     */
    public function syncFaqItems(CmsPage $page, array $items): void;
}
