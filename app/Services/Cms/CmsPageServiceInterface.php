<?php

namespace App\Services\Cms;

use App\Enums\CmsPageKey;
use App\Models\CmsPage;
use Illuminate\Support\Collection;

interface CmsPageServiceInterface
{
    /**
     * @return Collection<int, CmsPage>
     */
    public function listForAdmin(): Collection;

    public function pageForAdmin(CmsPageKey $key): CmsPage;

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(CmsPage $page, array $input): CmsPage;

    /**
     * @return array{
     *     about: ?string,
     *     contact: ?string,
     *     faq: ?string,
     *     terms: ?string,
     *     privacy: ?string
     * }
     */
    public function publishedPageBodies(): array;

    /**
     * @return list<array{id: int, question: string, answer: string}>
     */
    public function publishedFaqItems(): array;

    /**
     * @return array<string, array{title: string, seo_title: ?string, meta_description: ?string, is_published: bool}>
     */
    public function publishedPageMeta(): array;
}
