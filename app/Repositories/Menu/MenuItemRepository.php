<?php

namespace App\Repositories\Menu;

use App\Models\MenuItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MenuItemRepository
{
    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator
    {
        return MenuItem::query()
            ->with('category')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function featured(int $limit = 4): Collection
    {
        return MenuItem::query()
            ->with('category')
            ->where('is_available', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
