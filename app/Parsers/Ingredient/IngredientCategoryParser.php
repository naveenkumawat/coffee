<?php

namespace App\Parsers\Ingredient;

use App\Models\IngredientCategory;
use App\Parsers\AbstractParser;
use App\Transfers\Ingredient\IngredientCategoryTransferInterface;

class IngredientCategoryParser extends AbstractParser implements IngredientCategoryParserInterface
{
    public function getTransferFromModelEntity(IngredientCategory $ingredientCategory): IngredientCategoryTransferInterface
    {
        $transfer = $this->make(IngredientCategoryTransferInterface::class);
        $transfer->setId($ingredientCategory->getKey());
        $transfer->setName($ingredientCategory->name);
        $transfer->setDescription($ingredientCategory->description);
        $transfer->setIsActive((bool) $ingredientCategory->is_active);
        $transfer->setCreatedAt($ingredientCategory->created_at);
        $transfer->setUpdatedAt($ingredientCategory->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $categoryData): IngredientCategoryTransferInterface
    {
        $transfer = $this->make(IngredientCategoryTransferInterface::class);
        $transfer->setName(trim((string) $categoryData['name']));
        $transfer->setDescription(filled($categoryData['description'] ?? null) ? trim((string) $categoryData['description']) : null);
        $transfer->setIsActive((bool) ($categoryData['is_active'] ?? true));

        return $transfer;
    }
}
