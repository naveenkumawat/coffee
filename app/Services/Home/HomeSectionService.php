<?php

namespace App\Services\Home;

use App\Models\HomeSection;
use App\Models\Product;
use App\Repositories\Home\HomeSectionRepositoryInterface;
use App\Services\Merchandising\MerchandisingServiceInterface;
use App\Services\Product\ProductCatalogServiceInterface;
use App\Transfers\Home\HomeSectionTransferInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HomeSectionService implements HomeSectionServiceInterface
{
    public function __construct(
        protected HomeSectionRepositoryInterface $sections,
        protected ProductCatalogServiceInterface $catalog,
        protected MerchandisingServiceInterface $merchandising,
    ) {}

    public function store(HomeSectionTransferInterface $data): HomeSection
    {
        $section = DB::transaction(function () use ($data): HomeSection {
            $attributes = $data->toArray();
            $attributes['slug'] = $this->uniqueSlug(
                filled($data->getSlug()) ? (string) $data->getSlug() : (string) $data->getTitle(),
            );

            return $this->sections->create($attributes);
        });

        $this->flushCaches();

        return $section;
    }

    public function update(HomeSection $homeSection, HomeSectionTransferInterface $data): HomeSection
    {
        $section = DB::transaction(function () use ($homeSection, $data): HomeSection {
            $attributes = $data->toArray();
            $attributes['slug'] = $this->uniqueSlug(
                filled($data->getSlug()) ? (string) $data->getSlug() : (string) $data->getTitle(),
                (int) $homeSection->getKey(),
            );

            return $this->sections->update($homeSection, $attributes);
        });

        $this->flushCaches();

        return $section;
    }

    public function delete(HomeSection $homeSection): void
    {
        DB::transaction(function () use ($homeSection): void {
            $homeSection->forceFill(['is_active' => false])->save();
            $this->sections->delete($homeSection);
        });

        $this->flushCaches();
    }

    public function setActive(HomeSection $homeSection, bool $isActive): HomeSection
    {
        $section = $this->sections->setActive($homeSection, $isActive);
        $this->flushCaches();

        return $section;
    }

    public function move(HomeSection $homeSection, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Invalid reorder direction.',
            ]);
        }

        DB::transaction(fn () => $this->sections->moveSection($homeSection, $direction));
        $this->flushCaches();
    }

    public function attachProduct(HomeSection $homeSection, Product $product): void
    {
        if (! $product->is_active || ! $product->is_available) {
            throw ValidationException::withMessages([
                'product_id' => 'Only active and available products can be added to homepage sections.',
            ]);
        }

        DB::transaction(fn () => $this->sections->attachProduct($homeSection, (int) $product->getKey()));
        $this->flushCaches();
    }

    public function detachProduct(HomeSection $homeSection, Product $product): void
    {
        DB::transaction(fn () => $this->sections->detachProduct($homeSection, (int) $product->getKey()));
        $this->flushCaches();
    }

    public function moveProduct(HomeSection $homeSection, Product $product, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages([
                'direction' => 'Invalid reorder direction.',
            ]);
        }

        DB::transaction(function () use ($homeSection, $product, $direction): void {
            $orderedIds = $homeSection->sectionProducts()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            $index = $orderedIds->search((int) $product->getKey());

            if ($index === false) {
                throw ValidationException::withMessages([
                    'product_id' => 'This product is not in the section.',
                ]);
            }

            $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

            if ($swapWith < 0 || $swapWith >= $orderedIds->count()) {
                return;
            }

            $ids = $orderedIds->all();
            [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

            $this->sections->reorderProducts($homeSection, $ids);
        });

        $this->flushCaches();
    }

    public function activeSectionsForCustomer(): Collection
    {
        $sections = $this->sections->activeForHome();

        return $sections
            ->map(function (HomeSection $section): HomeSection {
                $products = $section->products;
                $maxItems = $section->max_items;

                if ($maxItems !== null && $maxItems > 0) {
                    $products = $products->take($maxItems)->values();
                    $section->setRelation('products', $products);
                }

                return $section;
            })
            ->filter(fn (HomeSection $section): bool => $section->products->isNotEmpty())
            ->values();
    }

    protected function flushCaches(): void
    {
        $this->catalog->flushPublicCache();
        $this->merchandising->flushConfigCache();
    }

    protected function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'section';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->sections->slugExists($slug, $ignoreId)) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }
}
