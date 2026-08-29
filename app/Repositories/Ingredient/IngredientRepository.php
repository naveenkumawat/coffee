<?php

namespace App\Repositories\Ingredient;

use App\Models\Ingredient;
use App\Models\IngredientBrand;
use App\Models\IngredientCategory;
use App\Repositories\AbstractRepository;
use App\Transfers\Ingredient\IngredientFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IngredientRepository extends AbstractRepository implements IngredientRepositoryInterface
{
    public function __construct(
        protected Ingredient $model,
    ) {}

    public function paginateForAdmin(IngredientFilterTransferInterface $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['brand', 'category'])
            ->when($filters->hasSearch(), function ($query) use ($filters): void {
                $search = $filters->getSearch();

                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('supplier_name', 'like', "%{$search}%")
                        ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters->getIngredientCategoryId(), fn ($query) => $query->where('ingredient_category_id', $filters->getIngredientCategoryId()))
            ->when($filters->getIngredientBrandId(), fn ($query) => $query->where('ingredient_brand_id', $filters->getIngredientBrandId()))
            ->when($filters->getMeasurementUnit(), fn ($query) => $query->where('measurement_unit', $filters->getMeasurementUnit()))
            ->when($filters->getStatus() === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters->getStatus() === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForCategory(IngredientCategory $ingredientCategory, int $perPage = 12): LengthAwarePaginator
    {
        return $ingredientCategory->ingredients()
            ->with('brand')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function paginateForBrand(IngredientBrand $ingredientBrand, int $perPage = 12): LengthAwarePaginator
    {
        return $ingredientBrand->ingredients()
            ->with('category')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $attributes): Ingredient
    {
        /** @var Ingredient $ingredient */
        $ingredient = $this->persist($this->model->newInstance(), $attributes);

        return $ingredient;
    }

    public function update(Ingredient $ingredient, array $attributes): Ingredient
    {
        /** @var Ingredient $ingredient */
        $ingredient = $this->persist($ingredient, $attributes);

        return $ingredient;
    }

    public function delete(Ingredient $ingredient): void
    {
        $this->remove($ingredient);
    }
}
