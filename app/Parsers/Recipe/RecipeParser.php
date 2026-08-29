<?php

namespace App\Parsers\Recipe;

use App\Models\Recipe;
use App\Parsers\AbstractParser;
use App\Transfers\Recipe\RecipeFilterTransferInterface;
use App\Transfers\Recipe\RecipeTransferInterface;

class RecipeParser extends AbstractParser implements RecipeParserInterface
{
    public function getTransferFromModelEntity(Recipe $recipe): RecipeTransferInterface
    {
        $transfer = $this->make(RecipeTransferInterface::class);
        $transfer->setId($recipe->getKey());
        $transfer->setProductVariantId((int) $recipe->product_variant_id);
        $transfer->setPreparationNotes($recipe->preparation_notes);
        $transfer->setIsActive((bool) $recipe->is_active);
        $transfer->setLines($recipe->lines->map(function ($line): array {
            return [
                'id' => $line->getKey(),
                'ingredient_id' => (int) $line->ingredient_id,
                'quantity' => (string) $line->quantity,
                'measurement_unit' => $line->measurement_unit?->value,
                'sort_order' => (int) $line->sort_order,
            ];
        })->all());
        $transfer->setCreatedAt($recipe->created_at);
        $transfer->setUpdatedAt($recipe->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $recipeData): RecipeTransferInterface
    {
        $transfer = $this->make(RecipeTransferInterface::class);
        $transfer->setProductVariantId((int) $recipeData['product_variant_id']);
        $transfer->setPreparationNotes(filled($recipeData['preparation_notes'] ?? null) ? trim((string) $recipeData['preparation_notes']) : null);
        $transfer->setIsActive((bool) ($recipeData['is_active'] ?? true));
        $transfer->setLines(collect($recipeData['lines'] ?? [])
            ->filter(fn (array $line): bool => filled($line['ingredient_id'] ?? null) || filled($line['quantity'] ?? null))
            ->values()
            ->map(function (array $line): array {
                return [
                    'id' => filled($line['id'] ?? null) ? (int) $line['id'] : null,
                    'ingredient_id' => filled($line['ingredient_id'] ?? null) ? (int) $line['ingredient_id'] : null,
                    'quantity' => filled($line['quantity'] ?? null) ? (string) $line['quantity'] : null,
                    'measurement_unit' => filled($line['measurement_unit'] ?? null) ? (string) $line['measurement_unit'] : null,
                    'sort_order' => (int) ($line['sort_order'] ?? 0),
                ];
            })
            ->all());

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): RecipeFilterTransferInterface
    {
        $transfer = $this->make(RecipeFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setProductCategoryId(filled($filterData['product_category_id'] ?? null) ? (int) $filterData['product_category_id'] : null);
        $transfer->setProductId(filled($filterData['product_id'] ?? null) ? (int) $filterData['product_id'] : null);
        $transfer->setIngredientId(filled($filterData['ingredient_id'] ?? null) ? (int) $filterData['ingredient_id'] : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);

        return $transfer;
    }
}
