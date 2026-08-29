<?php

namespace App\Parsers\Ingredient;

use App\Models\IngredientBrand;
use App\Parsers\AbstractParser;
use App\Transfers\Ingredient\IngredientBrandFilterTransferInterface;
use App\Transfers\Ingredient\IngredientBrandTransferInterface;

class IngredientBrandParser extends AbstractParser implements IngredientBrandParserInterface
{
    public function getTransferFromModelEntity(IngredientBrand $ingredientBrand): IngredientBrandTransferInterface
    {
        $transfer = $this->make(IngredientBrandTransferInterface::class);
        $transfer->setId($ingredientBrand->getKey());
        $transfer->setName($ingredientBrand->name);
        $transfer->setDescription($ingredientBrand->description);
        $transfer->setIsActive((bool) $ingredientBrand->is_active);
        $transfer->setCreatedAt($ingredientBrand->created_at);
        $transfer->setUpdatedAt($ingredientBrand->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $brandData): IngredientBrandTransferInterface
    {
        $transfer = $this->make(IngredientBrandTransferInterface::class);
        $transfer->setName(trim((string) $brandData['name']));
        $transfer->setDescription(filled($brandData['description'] ?? null) ? trim((string) $brandData['description']) : null);
        $transfer->setIsActive((bool) ($brandData['is_active'] ?? true));

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): IngredientBrandFilterTransferInterface
    {
        $transfer = $this->make(IngredientBrandFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);

        return $transfer;
    }
}
