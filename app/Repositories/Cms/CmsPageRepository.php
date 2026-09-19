<?php

namespace App\Repositories\Cms;

use App\Enums\CmsPageKey;
use App\Models\CmsFaqItem;
use App\Models\CmsPage;
use App\Repositories\AbstractRepository;
use Illuminate\Support\Collection;

class CmsPageRepository extends AbstractRepository implements CmsPageRepositoryInterface
{
    public function __construct(
        protected CmsPage $model,
    ) {}

    /**
     * @return Collection<int, CmsPage>
     */
    public function allForAdmin(): Collection
    {
        $order = array_map(fn (CmsPageKey $key): string => $key->value, CmsPageKey::ordered());

        return $this->model->newQuery()
            ->with('faqItems')
            ->get()
            ->sortBy(function (CmsPage $page) use ($order): int {
                $index = array_search($page->key, $order, true);

                return $index === false ? 1000 : $index;
            })
            ->values();
    }

    public function findByKey(CmsPageKey $key): ?CmsPage
    {
        return $this->model->newQuery()
            ->with('faqItems')
            ->where('key', $key->value)
            ->first();
    }

    public function savePage(CmsPage $page, array $attributes): CmsPage
    {
        return $this->persist($page, $attributes);
    }

    /**
     * @param  list<array{id?: int, question: string, answer: string, sort_order: int, is_active: bool}>  $items
     */
    public function syncFaqItems(CmsPage $page, array $items): void
    {
        $keptIds = [];
        $sort = 0;

        foreach ($items as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            $payload = [
                'question' => $item['question'],
                'answer' => $item['answer'],
                'sort_order' => $item['sort_order'] ?? $sort,
                'is_active' => $item['is_active'],
            ];

            if ($id > 0) {
                $existing = CmsFaqItem::query()
                    ->where('cms_page_id', $page->getKey())
                    ->whereKey($id)
                    ->first();

                if ($existing instanceof CmsFaqItem) {
                    $this->persist($existing, $payload);
                    $keptIds[] = (int) $existing->getKey();
                    $sort += 10;

                    continue;
                }
            }

            $created = CmsFaqItem::query()->create([
                'cms_page_id' => $page->getKey(),
                ...$payload,
            ]);
            $keptIds[] = (int) $created->getKey();
            $sort += 10;
        }

        CmsFaqItem::query()
            ->where('cms_page_id', $page->getKey())
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn ($query) => $query)
            ->delete();
    }
}
