<?php

namespace App\Services\Recipe;

use App\Models\Recipe;

class RecipeCostingService implements RecipeCostingServiceInterface
{
    public function summarize(Recipe $recipe): array
    {
        $recipe->loadMissing(['variant.product', 'lines.ingredient']);

        $lineSummaries = [];
        $productionCost = '0.0000';
        $sellingPrice = $recipe->variant ? bcdiv((string) $recipe->variant->price, '1', 4) : '0.0000';

        foreach ($recipe->lines as $line) {
            $ingredientUnitCost = $line->ingredient ? bcdiv((string) $line->ingredient->cost_per_unit, '1', 4) : '0.0000';
            $lineCost = bcmul($ingredientUnitCost, (string) $line->base_quantity, 4);
            $productionCost = bcadd($productionCost, $lineCost, 4);

            $lineSummaries[] = [
                'id' => $line->getKey(),
                'ingredient_name' => $line->ingredient?->name,
                'ingredient_base_unit' => $line->ingredient?->base_measurement_unit?->value,
                'quantity' => (string) $line->quantity,
                'measurement_unit' => $line->measurement_unit?->value,
                'base_quantity' => (string) $line->base_quantity,
                'base_measurement_unit' => $line->base_measurement_unit?->value,
                'ingredient_cost_per_unit' => $ingredientUnitCost,
                'line_cost' => $lineCost,
            ];
        }

        $grossProfit = bcsub($sellingPrice, $productionCost, 4);
        $marginPercentage = bccomp($sellingPrice, '0.0000', 4) === 1
            ? bcmul(bcdiv($grossProfit, $sellingPrice, 6), '100', 2)
            : '0.00';

        return [
            'production_cost' => $productionCost,
            'selling_price' => $sellingPrice,
            'gross_profit' => $grossProfit,
            'margin_percentage' => $marginPercentage,
            'lines' => $lineSummaries,
        ];
    }
}
