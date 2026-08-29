<?php

namespace App\Repositories\Ingredient;

use App\Models\IngredientCategory;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class IngredientCategoryRepository extends AbstractRepository implements IngredientCategoryRepositoryInterface
{
    public function __construct(
        protected IngredientCategory $model,
    ) {}

    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount([
                'ingredients' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage);
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

    public function create(array $attributes): IngredientCategory
    {
        /** @var IngredientCategory $ingredientCategory */
        $ingredientCategory = $this->persist($this->model->newInstance(), $attributes);

        return $ingredientCategory;
    }

    public function update(IngredientCategory $ingredientCategory, array $attributes): IngredientCategory
    {
        /** @var IngredientCategory $ingredientCategory */
        $ingredientCategory = $this->persist($ingredientCategory, $attributes);

        return $ingredientCategory;
    }

    public function delete(IngredientCategory $ingredientCategory): void
    {
        $this->remove($ingredientCategory);
    }

    public function hasIngredients(IngredientCategory $ingredientCategory): bool
    {
        return $ingredientCategory->ingredients()->withTrashed()->exists();
    }

    public function activeCategories(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function findAvailableSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'category';
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
