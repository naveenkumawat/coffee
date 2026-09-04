<?php

namespace App\Repositories\Home;

use App\Models\HomeSection;
use App\Models\HomeSectionProduct;
use App\Repositories\AbstractRepository;
use App\Transfers\Home\HomeSectionFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class HomeSectionRepository extends AbstractRepository implements HomeSectionRepositoryInterface
{
    public function __construct(
        protected HomeSection $model,
        protected HomeSectionProduct $pivotModel,
    ) {}

    public function paginateForAdmin(HomeSectionFilterTransferInterface $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount('sectionProducts')
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nested) use ($search): void {
                    $nested
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%");
                });
            })
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeForHome(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->where('placement', 'home')
            ->where('source_type', 'curated')
            ->with([
                'products' => function ($query): void {
                    $query
                        ->where('products.is_active', true)
                        ->where('products.is_available', true)
                        ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('is_active', true))
                        ->with([
                            'category',
                            'flavours',
                            'tags' => fn ($tagQuery) => $tagQuery->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                            'defaultVariant.recipe.lines' => fn ($lineQuery) => $lineQuery
                                ->where('show_to_customer', true)
                                ->orderBy('sort_order')
                                ->orderBy('id'),
                            'defaultVariant.recipe.lines.ingredient',
                            'variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->where('is_available', true),
                            'variants.recipe.lines' => fn ($lineQuery) => $lineQuery
                                ->where('show_to_customer', true)
                                ->orderBy('sort_order')
                                ->orderBy('id'),
                            'variants.recipe.lines.ingredient',
                        ])
                        ->withAvg('ratings as ratings_avg_rating', 'rating')
                        ->withCount('ratings')
                        ->orderBy('home_section_products.sort_order')
                        ->orderBy('products.name');
                },
            ])
            ->orderByDesc('priority')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();
    }

    public function create(array $attributes): HomeSection
    {
        /** @var HomeSection $section */
        $section = $this->persist($this->model->newInstance(), $attributes);

        return $section;
    }

    public function update(HomeSection $homeSection, array $attributes): HomeSection
    {
        /** @var HomeSection $section */
        $section = $this->persist($homeSection, $attributes);

        return $section;
    }

    public function delete(HomeSection $homeSection): void
    {
        $homeSection->products()->detach();
        $this->remove($homeSection);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function attachProduct(HomeSection $homeSection, int $productId, ?int $sortOrder = null): void
    {
        if ($homeSection->products()->whereKey($productId)->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is already in the section.',
            ]);
        }

        $nextOrder = $sortOrder ?? (((int) $homeSection->sectionProducts()->max('sort_order')) + 10);

        $homeSection->products()->attach($productId, [
            'sort_order' => $nextOrder,
        ]);
    }

    public function detachProduct(HomeSection $homeSection, int $productId): void
    {
        $homeSection->products()->detach($productId);
    }

    public function reorderProducts(HomeSection $homeSection, SupportCollection|array $orderedProductIds): void
    {
        $ids = collect($orderedProductIds)->map(fn ($id): int => (int) $id)->values();

        foreach ($ids as $index => $productId) {
            $this->pivotModel->newQuery()
                ->where('home_section_id', $homeSection->getKey())
                ->where('product_id', $productId)
                ->update(['sort_order' => ($index + 1) * 10]);
        }
    }

    public function moveSection(HomeSection $homeSection, string $direction): void
    {
        $placement = $homeSection->placement?->value ?? (string) $homeSection->placement;

        $siblings = $this->model->newQuery()
            ->where('placement', $placement)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(fn (HomeSection $section): bool => (int) $section->getKey() === (int) $homeSection->getKey());

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= $siblings->count()) {
            return;
        }

        /** @var HomeSection $current */
        $current = $siblings[$index];
        /** @var HomeSection $neighbor */
        $neighbor = $siblings[$swapWith];

        $currentOrder = (int) $current->sort_order;
        $neighborOrder = (int) $neighbor->sort_order;

        if ($currentOrder === $neighborOrder) {
            $currentOrder = ($index + 1) * 10;
            $neighborOrder = ($swapWith + 1) * 10;
        }

        $current->forceFill(['sort_order' => $neighborOrder])->save();
        $neighbor->forceFill(['sort_order' => $currentOrder])->save();
    }

    public function setActive(HomeSection $homeSection, bool $isActive): HomeSection
    {
        $homeSection->forceFill(['is_active' => $isActive])->save();

        return $homeSection->refresh();
    }
}
