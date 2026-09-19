<?php

namespace App\Services\Cms;

use App\Enums\CmsPageKey;
use App\Models\CmsPage;
use App\Repositories\Cms\CmsPageRepositoryInterface;
use App\Services\PublicCache\PublicCacheVersionServiceInterface;
use App\Support\SafeHtml;
use Illuminate\Support\Collection;

class CmsPageService implements CmsPageServiceInterface
{
    public function __construct(
        protected CmsPageRepositoryInterface $pages,
    ) {}

    /**
     * @return Collection<int, CmsPage>
     */
    public function listForAdmin(): Collection
    {
        $this->ensureSystemPagesExist();

        return $this->pages->allForAdmin();
    }

    public function pageForAdmin(CmsPageKey $key): CmsPage
    {
        $this->ensureSystemPagesExist();

        $page = $this->pages->findByKey($key);

        if (! $page instanceof CmsPage) {
            abort(404);
        }

        return $page;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(CmsPage $page, array $input): CmsPage
    {
        $page = $this->pages->savePage($page, [
            'title' => trim((string) ($input['title'] ?? $page->title)),
            'seo_title' => $this->nullableString($input['seo_title'] ?? null),
            'meta_description' => $this->nullableString($input['meta_description'] ?? null),
            'body' => SafeHtml::sanitize(isset($input['body']) ? (string) $input['body'] : null),
            'is_published' => (bool) ($input['is_published'] ?? false),
        ]);

        if ($page->pageKey() === CmsPageKey::Faq) {
            $this->pages->syncFaqItems($page, $this->normalizedFaqItems($input['faq_items'] ?? []));
        }

        app(PublicCacheVersionServiceInterface::class)->invalidate('cms_page');

        return $page->fresh(['faqItems']) ?? $page;
    }

    /**
     * @return array{
     *     about: ?string,
     *     contact: ?string,
     *     faq: ?string,
     *     terms: ?string,
     *     privacy: ?string
     * }
     */
    public function publishedPageBodies(): array
    {
        $this->ensureSystemPagesExist();

        $bodies = [
            'about' => null,
            'contact' => null,
            'faq' => null,
            'terms' => null,
            'privacy' => null,
        ];

        foreach ($this->pages->allForAdmin() as $page) {
            $key = $page->pageKey();

            if ($key === null || ! $page->is_published) {
                continue;
            }

            if ($key === CmsPageKey::Faq) {
                continue;
            }

            $bodies[$key->value] = SafeHtml::sanitize($page->body);
        }

        return $bodies;
    }

    /**
     * @return list<array{id: int, question: string, answer: string}>
     */
    public function publishedFaqItems(): array
    {
        $this->ensureSystemPagesExist();

        $page = $this->pages->findByKey(CmsPageKey::Faq);

        if (! $page instanceof CmsPage || ! $page->is_published) {
            return [];
        }

        $items = [];

        foreach ($page->faqItems as $item) {
            if (! $item->is_active) {
                continue;
            }

            $question = trim((string) $item->question);
            $answer = trim((string) $item->answer);

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = [
                'id' => (int) $item->getKey(),
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{title: string, seo_title: ?string, meta_description: ?string, is_published: bool}>
     */
    public function publishedPageMeta(): array
    {
        $meta = [];

        foreach ($this->pages->allForAdmin() as $page) {
            $key = $page->pageKey();

            if ($key === null) {
                continue;
            }

            $meta[$key->value] = [
                'title' => (string) $page->title,
                'seo_title' => $page->seo_title,
                'meta_description' => $page->meta_description,
                'is_published' => (bool) $page->is_published,
            ];
        }

        return $meta;
    }

    protected function ensureSystemPagesExist(): void
    {
        foreach (CmsPageKey::ordered() as $key) {
            if ($this->pages->findByKey($key) instanceof CmsPage) {
                continue;
            }

            $this->pages->savePage(new CmsPage, [
                'key' => $key->value,
                'title' => $key->title(),
                'seo_title' => null,
                'meta_description' => null,
                'body' => null,
                'is_published' => false,
                'is_system' => true,
            ]);
        }
    }

    /**
     * @return list<array{id?: int, question: string, answer: string, sort_order: int, is_active: bool}>
     */
    protected function normalizedFaqItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        $sort = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! empty($item['_delete'])) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            $row = [
                'question' => mb_substr($question, 0, 255),
                'answer' => $answer,
                'sort_order' => is_numeric($item['sort_order'] ?? null) ? (int) $item['sort_order'] : $sort,
                'is_active' => ! empty($item['is_active']),
            ];

            if (isset($item['id']) && is_numeric($item['id']) && (int) $item['id'] > 0) {
                $row['id'] = (int) $item['id'];
            }

            $normalized[] = $row;
            $sort += 10;
        }

        usort($normalized, fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order']);

        return $normalized;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
