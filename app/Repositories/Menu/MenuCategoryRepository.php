<?php

namespace App\Repositories\Menu;

use App\Models\MenuCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MenuCategoryRepository
{
    public function paginateForAdmin(int $perPage = 12): LengthAwarePaginator
    {
        return MenuCategory::query()
            ->withCount('menuItems')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function activeWithItems(): Collection
    {
        return MenuCategory::query()
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
}
