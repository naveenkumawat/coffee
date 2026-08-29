<?php

namespace App\Repositories\Menu;

use App\Models\MenuItem;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MenuItemRepository extends AbstractRepository implements MenuItemRepositoryInterface
{
    public function __construct(
        protected MenuItem $model,
    ) {}

    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function featured(int $limit = 4): Collection
    {
        return $this->model->newQuery()
            ->with('category')
            ->where('is_available', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function create(array $attributes): MenuItem
    {
        /** @var MenuItem $menuItem */
        $menuItem = $this->persist($this->model->newInstance(), $attributes);

        return $menuItem;
    }

    public function update(MenuItem $menuItem, array $attributes): MenuItem
    {
        /** @var MenuItem $menuItem */
        $menuItem = $this->persist($menuItem, $attributes);

        return $menuItem;
    }

    public function delete(MenuItem $menuItem): void
    {
        $this->remove($menuItem);
    }
}
