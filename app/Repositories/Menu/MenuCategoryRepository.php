<?php

namespace App\Repositories\Menu;

use App\Models\MenuCategory;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MenuCategoryRepository extends AbstractRepository implements MenuCategoryRepositoryInterface
{
    public function __construct(
        protected MenuCategory $model,
    ) {}

    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function activeWithItems(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->with([
                'menuItems' => fn ($query) => $query
                    ->where('is_available', true)
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function activeOptions(): array
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function allOptions(): array
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function create(array $attributes): MenuCategory
    {
        /** @var MenuCategory $menuCategory */
        $menuCategory = $this->persist($this->model->newInstance(), $attributes);

        return $menuCategory;
    }

    public function update(MenuCategory $menuCategory, array $attributes): MenuCategory
    {
        /** @var MenuCategory $menuCategory */
        $menuCategory = $this->persist($menuCategory, $attributes);

        return $menuCategory;
    }

    public function delete(MenuCategory $menuCategory): void
    {
        $this->remove($menuCategory);
    }
}
