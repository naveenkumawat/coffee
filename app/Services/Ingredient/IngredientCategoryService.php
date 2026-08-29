<?php

namespace App\Services\Ingredient;

use App\Models\IngredientCategory;
use App\Repositories\Ingredient\IngredientCategoryRepositoryInterface;
use App\Transfers\Ingredient\IngredientCategoryTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngredientCategoryService implements IngredientCategoryServiceInterface
{
    public function __construct(
        protected IngredientCategoryRepositoryInterface $categories,
    ) {}

    public function store(IngredientCategoryTransferInterface $data): IngredientCategory
    {
        return DB::transaction(function () use ($data): IngredientCategory {
            return $this->categories->create($this->prepareAttributes($data));
        });
    }

    public function update(IngredientCategory $ingredientCategory, IngredientCategoryTransferInterface $data): IngredientCategory
    {
        return DB::transaction(function () use ($ingredientCategory, $data): IngredientCategory {
            return $this->categories->update($ingredientCategory, $this->prepareAttributes($data, $ingredientCategory));
        });
    }

    public function delete(IngredientCategory $ingredientCategory): void
    {
        if ($this->categories->hasIngredients($ingredientCategory)) {
            throw ValidationException::withMessages([
                'category' => 'Archive or move ingredients before removing this category.',
            ]);
        }

        DB::transaction(function () use ($ingredientCategory): void {
            $ingredientCategory->forceFill(['is_active' => false])->save();
            $this->categories->delete($ingredientCategory);
        });
    }

    protected function prepareAttributes(IngredientCategoryTransferInterface $data, ?IngredientCategory $ingredientCategory = null): array
    {
        return [
            'name' => $data->getName(),
            'slug' => $this->categories->findAvailableSlug((string) $data->getName(), $ingredientCategory?->id),
            'description' => $data->getDescription(),
            'is_active' => $data->isActive(),
        ];
    }
}
