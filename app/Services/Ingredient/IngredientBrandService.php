<?php

namespace App\Services\Ingredient;

use App\Models\IngredientBrand;
use App\Repositories\Ingredient\IngredientBrandRepositoryInterface;
use App\Transfers\Ingredient\IngredientBrandTransferInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IngredientBrandService implements IngredientBrandServiceInterface
{
    public function __construct(
        protected IngredientBrandRepositoryInterface $brands,
    ) {}

    public function store(IngredientBrandTransferInterface $data): IngredientBrand
    {
        return DB::transaction(function () use ($data): IngredientBrand {
            return $this->brands->create($this->prepareAttributes($data));
        });
    }

    public function update(IngredientBrand $ingredientBrand, IngredientBrandTransferInterface $data): IngredientBrand
    {
        return DB::transaction(function () use ($ingredientBrand, $data): IngredientBrand {
            return $this->brands->update($ingredientBrand, $this->prepareAttributes($data, $ingredientBrand));
        });
    }

    public function delete(IngredientBrand $ingredientBrand): void
    {
        if ($this->brands->hasIngredients($ingredientBrand)) {
            throw ValidationException::withMessages([
                'brand' => 'Archive or move ingredients before removing this brand.',
            ]);
        }

        DB::transaction(function () use ($ingredientBrand): void {
            $ingredientBrand->forceFill(['is_active' => false])->save();
            $this->brands->delete($ingredientBrand);
        });
    }

    protected function prepareAttributes(IngredientBrandTransferInterface $data, ?IngredientBrand $ingredientBrand = null): array
    {
        return [
            'name' => $data->getName(),
            'slug' => $this->brands->findAvailableSlug((string) $data->getName(), $ingredientBrand?->id),
            'description' => $data->getDescription(),
            'is_active' => $data->isActive(),
        ];
    }
}
