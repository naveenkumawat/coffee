<?php

namespace App\Repositories\Ingredient;

use App\Models\IngredientBrand;
use App\Repositories\AbstractRepository;
use App\Transfers\Ingredient\IngredientBrandFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class IngredientBrandRepository extends AbstractRepository implements IngredientBrandRepositoryInterface
{
    public function __construct(
        protected IngredientBrand $model,
    ) {}

    public function paginateForAdmin(IngredientBrandFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount([
                'ingredients' => fn ($query) => $query->withTrashed(),
            ])
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeOptions(): array
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function allOptions(): array
    {
        return $this->model->newQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function create(array $attributes): IngredientBrand
    {
        /** @var IngredientBrand $ingredientBrand */
        $ingredientBrand = $this->persist($this->model->newInstance(), $attributes);

        return $ingredientBrand;
    }

    public function update(IngredientBrand $ingredientBrand, array $attributes): IngredientBrand
    {
        /** @var IngredientBrand $ingredientBrand */
        $ingredientBrand = $this->persist($ingredientBrand, $attributes);

        return $ingredientBrand;
    }

    public function delete(IngredientBrand $ingredientBrand): void
    {
        $this->remove($ingredientBrand);
    }

    public function hasIngredients(IngredientBrand $ingredientBrand): bool
    {
        return $ingredientBrand->ingredients()->withTrashed()->exists();
    }

    public function findAvailableSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'brand';
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = sprintf('%s-%d', $baseSlug, $suffix);
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists();
    }
}
