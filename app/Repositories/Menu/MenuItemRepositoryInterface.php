<?php

namespace App\Repositories\Menu;

use App\Models\MenuItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface MenuItemRepositoryInterface
{
    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator;

    public function featured(int $limit = 4): Collection;

    public function create(array $attributes): MenuItem;

    public function update(MenuItem $menuItem, array $attributes): MenuItem;

    public function delete(MenuItem $menuItem): void;
}
