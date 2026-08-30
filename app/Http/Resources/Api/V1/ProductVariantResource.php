<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductVariant;
use App\Support\CustomerVisibleIngredients;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductVariant $variant */
        $variant = $this->resource;

        return [
            'id' => $variant->getKey(),
            'product_id' => $variant->product_id,
            'product_name' => $variant->product?->name,
            'name' => $variant->name,
            'serving_size' => [
                'value' => $variant->serving_size_value,
                'unit' => $variant->serving_size_unit?->value,
                'label' => trim(sprintf('%s %s', $variant->serving_size_value, $variant->serving_size_unit?->value ?? '')),
            ],
            'price' => $variant->price,
            'is_available' => (bool) $variant->is_available,
            'major_ingredients' => CustomerVisibleIngredients::forVariant($variant),
        ];
    }
}
