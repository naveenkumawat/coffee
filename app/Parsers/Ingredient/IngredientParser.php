<?php

namespace App\Parsers\Ingredient;

use App\Models\Ingredient;
use App\Parsers\AbstractParser;
use App\Transfers\Ingredient\IngredientFilterTransferInterface;
use App\Transfers\Ingredient\IngredientTransferInterface;

class IngredientParser extends AbstractParser implements IngredientParserInterface
{
    public function getTransferFromModelEntity(Ingredient $ingredient): IngredientTransferInterface
    {
        $transfer = $this->make(IngredientTransferInterface::class);
        $transfer->setId($ingredient->getKey());
        $transfer->setIngredientCategoryId((int) $ingredient->ingredient_category_id);
        $transfer->setIngredientBrandId($ingredient->ingredient_brand_id ? (int) $ingredient->ingredient_brand_id : null);
        $transfer->setName($ingredient->name);
        $transfer->setSlug($ingredient->slug);
        $transfer->setDescription($ingredient->description);
        $transfer->setMeasurementUnit($ingredient->measurement_unit?->value);
        $transfer->setPurchaseQuantity((string) $ingredient->purchase_quantity);
        $transfer->setPurchaseCost((string) $ingredient->purchase_cost);
        $transfer->setCurrentStock((string) $ingredient->current_stock);
        $transfer->setMinimumStock((string) $ingredient->minimum_stock);
        $transfer->setReorderLevel((string) $ingredient->reorder_level);
        $transfer->setSupplierName($ingredient->supplier_name);
        $transfer->setSupplierEmail($ingredient->supplier_email);
        $transfer->setSupplierPhone($ingredient->supplier_phone);
        $transfer->setSupplierNotes($ingredient->supplier_notes);
        $transfer->setIsActive((bool) $ingredient->is_active);
        $transfer->setCreatedAt($ingredient->created_at);
        $transfer->setUpdatedAt($ingredient->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $ingredientData): IngredientTransferInterface
    {
        $transfer = $this->make(IngredientTransferInterface::class);
        $transfer->setIngredientCategoryId((int) $ingredientData['ingredient_category_id']);
        $transfer->setIngredientBrandId(filled($ingredientData['ingredient_brand_id'] ?? null) ? (int) $ingredientData['ingredient_brand_id'] : null);
        $transfer->setName(trim((string) $ingredientData['name']));
        $transfer->setSlug(filled($ingredientData['slug'] ?? null) ? trim((string) $ingredientData['slug']) : null);
        $transfer->setDescription(filled($ingredientData['description'] ?? null) ? trim((string) $ingredientData['description']) : null);
        $transfer->setMeasurementUnit((string) $ingredientData['measurement_unit']);
        $transfer->setPurchaseQuantity((string) $ingredientData['purchase_quantity']);
        $transfer->setPurchaseCost((string) $ingredientData['purchase_cost']);
        $transfer->setCurrentStock(filled($ingredientData['current_stock'] ?? null) ? (string) $ingredientData['current_stock'] : '0');
        $transfer->setMinimumStock(filled($ingredientData['minimum_stock'] ?? null) ? (string) $ingredientData['minimum_stock'] : '0');
        $transfer->setReorderLevel(filled($ingredientData['reorder_level'] ?? null) ? (string) $ingredientData['reorder_level'] : '0');
        $transfer->setSupplierName(filled($ingredientData['supplier_name'] ?? null) ? trim((string) $ingredientData['supplier_name']) : null);
        $transfer->setSupplierEmail(filled($ingredientData['supplier_email'] ?? null) ? strtolower(trim((string) $ingredientData['supplier_email'])) : null);
        $transfer->setSupplierPhone(filled($ingredientData['supplier_phone'] ?? null) ? trim((string) $ingredientData['supplier_phone']) : null);
        $transfer->setSupplierNotes(filled($ingredientData['supplier_notes'] ?? null) ? trim((string) $ingredientData['supplier_notes']) : null);
        $transfer->setIsActive((bool) ($ingredientData['is_active'] ?? true));

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): IngredientFilterTransferInterface
    {
        $transfer = $this->make(IngredientFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setIngredientCategoryId(filled($filterData['ingredient_category_id'] ?? null) ? (int) $filterData['ingredient_category_id'] : null);
        $transfer->setIngredientBrandId(filled($filterData['ingredient_brand_id'] ?? null) ? (int) $filterData['ingredient_brand_id'] : null);
        $transfer->setMeasurementUnit(filled($filterData['measurement_unit'] ?? null) ? (string) $filterData['measurement_unit'] : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);

        return $transfer;
    }
}
