<?php

namespace Database\Seeders;

use App\Enums\CmsPageKey;
use App\Models\WebsiteSetting;
use App\Services\Cms\CmsPageServiceInterface;
use Illuminate\Database\Seeder;

/**
 * Local/testing CMS pages only. Copies preserved Website Settings page copy when present.
 */
class CmsPageSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $service = app(CmsPageServiceInterface::class);

        foreach (CmsPageKey::ordered() as $key) {
            $page = $service->pageForAdmin($key);
            $legacy = WebsiteSetting::query()->where('key', $key->legacySettingKey()->value)->value('value');
            $legacyText = is_string($legacy) ? trim($legacy) : '';

            if ($key === CmsPageKey::Faq) {
                if ($page->faqItems->isEmpty() && $legacyText !== '') {
                    $service->update($page, [
                        'title' => $page->title,
                        'seo_title' => $page->seo_title,
                        'meta_description' => $page->meta_description,
                        'body' => null,
                        'is_published' => true,
                        'faq_items' => $this->legacyFaqItems($legacyText),
                    ]);
                }

                continue;
            }

            if (filled($page->body)) {
                continue;
            }

            if ($legacyText === '') {
                continue;
            }

            $service->update($page, [
                'title' => $page->title,
                'seo_title' => $page->seo_title,
                'meta_description' => $page->meta_description,
                'body' => nl2br(e($legacyText), false),
                'is_published' => true,
            ]);
        }
    }

    /**
     * @return list<array{question: string, answer: string, sort_order: int, is_active: bool}>
     */
    protected function legacyFaqItems(string $legacy): array
    {
        $items = [];
        $sort = 0;

        foreach (preg_split("/\n\s*\n/", trim($legacy)) ?: [] as $block) {
            $block = trim($block);
            $lines = array_values(array_filter(array_map('trim', preg_split("/\n/", $block) ?: [])));

            if (count($lines) < 2) {
                continue;
            }

            $items[] = [
                'question' => $lines[0],
                'answer' => implode("\n", array_slice($lines, 1)),
                'sort_order' => $sort,
                'is_active' => true,
            ];
            $sort += 10;
        }

        return $items;
    }
}
