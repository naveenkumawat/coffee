<?php

namespace App\Parsers\Product;

use App\Models\Product;
use App\Parsers\AbstractParser;
use App\Transfers\Product\ProductFilterTransferInterface;
use App\Transfers\Product\ProductTransferInterface;

class ProductParser extends AbstractParser implements ProductParserInterface
{
    public function getTransferFromModelEntity(Product $product): ProductTransferInterface
    {
        $transfer = $this->make(ProductTransferInterface::class);
        $transfer->setId($product->getKey());
        $transfer->setProductCategoryId((int) $product->product_category_id);
        $transfer->setName($product->name);
        $transfer->setSku($product->sku);
        $transfer->setShortDescription($product->short_description);
        $transfer->setDescription($product->description);
        $transfer->setCustomerIngredientSummary($product->customer_ingredient_summary);
        $transfer->setImagePath($product->image_path);
        $transfer->setPreparationTimeMinutes($product->preparation_time_minutes ? (int) $product->preparation_time_minutes : null);
        $transfer->setSortOrder((int) $product->sort_order);
        $transfer->setProductFlavourIds($product->flavours()->pluck('product_flavours.id')->map(fn ($id): int => (int) $id)->all());
        $transfer->setProductTagIds($product->tags()->pluck('product_tags.id')->map(fn ($id): int => (int) $id)->all());
        $transfer->setVariants($product->variants->map(function ($variant): array {
            return [
                'id' => $variant->getKey(),
                'name' => $variant->name,
                'serving_size_value' => (string) $variant->serving_size_value,
                'serving_size_unit' => $variant->serving_size_unit?->value,
                'price' => (string) $variant->price,
                'sort_order' => (int) $variant->sort_order,
                'is_active' => (bool) $variant->is_active,
                'is_available' => (bool) $variant->is_available,
            ];
        })->all());
        $transfer->setIsActive((bool) $product->is_active);
        $transfer->setIsAvailable((bool) $product->is_available);
        $transfer->setIsFeatured((bool) $product->is_featured);
        $transfer->setIsNew((bool) $product->is_new);
        $transfer->setIsBestseller((bool) $product->is_bestseller);
        $transfer->setIsVegetarian((bool) $product->is_vegetarian);
        $transfer->setIsCustomizable((bool) $product->is_customizable);
        $transfer->setCreatedAt($product->created_at);
        $transfer->setUpdatedAt($product->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $productData): ProductTransferInterface
    {
        $transfer = $this->make(ProductTransferInterface::class);
        $transfer->setProductCategoryId((int) $productData['product_category_id']);
        $transfer->setName(trim((string) $productData['name']));
        $transfer->setSku(filled($productData['sku'] ?? null) ? trim((string) $productData['sku']) : null);
        $transfer->setShortDescription(filled($productData['short_description'] ?? null) ? trim((string) $productData['short_description']) : null);
        $transfer->setDescription(filled($productData['description'] ?? null) ? trim((string) $productData['description']) : null);
        $transfer->setCustomerIngredientSummary(filled($productData['customer_ingredient_summary'] ?? null) ? trim((string) $productData['customer_ingredient_summary']) : null);
        $transfer->setImagePath(filled($productData['image_path'] ?? null) ? trim((string) $productData['image_path']) : null);
        $transfer->setPreparationTimeMinutes(filled($productData['preparation_time_minutes'] ?? null) ? (int) $productData['preparation_time_minutes'] : null);
        $transfer->setSortOrder((int) ($productData['sort_order'] ?? 0));
        $transfer->setProductFlavourIds(collect($productData['product_flavour_ids'] ?? [])->filter(fn ($id) => filled($id))->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $transfer->setProductTagIds(collect($productData['product_tag_ids'] ?? [])->filter(fn ($id) => filled($id))->map(fn ($id): int => (int) $id)->unique()->values()->all());
        $transfer->setVariants(collect($productData['variants'] ?? [])->filter(function (array $variant): bool {
            return filled($variant['name'] ?? null) || filled($variant['price'] ?? null) || filled($variant['serving_size_value'] ?? null);
        })->values()->map(function (array $variant): array {
            return [
                'id' => filled($variant['id'] ?? null) ? (int) $variant['id'] : null,
                'name' => trim((string) ($variant['name'] ?? '')),
                'serving_size_value' => filled($variant['serving_size_value'] ?? null) ? (string) $variant['serving_size_value'] : null,
                'serving_size_unit' => filled($variant['serving_size_unit'] ?? null) ? (string) $variant['serving_size_unit'] : null,
                'price' => filled($variant['price'] ?? null) ? (string) $variant['price'] : null,
                'sort_order' => (int) ($variant['sort_order'] ?? 0),
                'is_active' => (bool) ($variant['is_active'] ?? true),
                'is_available' => (bool) ($variant['is_available'] ?? true),
            ];
        })->all());
        $transfer->setIsActive((bool) ($productData['is_active'] ?? true));
        $transfer->setIsAvailable((bool) ($productData['is_available'] ?? true));
        $transfer->setIsFeatured(false);
        $transfer->setIsNew(false);
        $transfer->setIsBestseller(false);
        $transfer->setIsVegetarian((bool) ($productData['is_vegetarian'] ?? false));
        $transfer->setIsCustomizable((bool) ($productData['is_customizable'] ?? false));

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): ProductFilterTransferInterface
    {
        $transfer = $this->make(ProductFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setProductCategoryId(filled($filterData['product_category_id'] ?? null) ? (int) $filterData['product_category_id'] : null);
        $transfer->setProductFlavourId(filled($filterData['product_flavour_id'] ?? null) ? (int) $filterData['product_flavour_id'] : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);
        $transfer->setAvailability(filled($filterData['availability'] ?? null) ? (string) $filterData['availability'] : null);
        $transfer->setFeatured(filled($filterData['featured'] ?? null) ? (string) $filterData['featured'] : null);

        return $transfer;
    }
}
