<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->getKey(),
            'category' => $product->category ? new ProductCategoryResource($product->category) : null,
            'flavours' => ProductFlavourResource::collection($product->flavours),
            'default_variant' => $product->defaultVariant ? new ProductVariantResource($product->defaultVariant) : null,
            'variants' => ProductVariantResource::collection($product->variants),
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'customer_ingredient_summary' => $product->customer_ingredient_summary,
            'image_path' => $product->image_path,
            'preparation_time_minutes' => $product->preparation_time_minutes,
            'is_featured' => (bool) $product->is_featured,
            'is_new' => (bool) $product->is_new,
            'is_bestseller' => (bool) $product->is_bestseller,
            'is_vegetarian' => (bool) $product->is_vegetarian,
            'is_customizable' => (bool) $product->is_customizable,
        ];
    }
}
