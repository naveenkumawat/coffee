<?php

namespace App\Repositories\Menu;

use App\Models\MenuCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MenuCategoryRepositoryInterface
{
    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator;

    public function activeWithItems(): Collection;

    public function activeOptions(): array;

    public function allOptions(): array;

    public function create(array $attributes): MenuCategory;

    public function update(MenuCategory $menuCategory, array $attributes): MenuCategory;

    public function delete(MenuCategory $menuCategory): void;
}
