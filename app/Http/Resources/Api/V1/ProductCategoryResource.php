<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductCategory;
use App\Support\PublicMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductCategory $category */
        $category = $this->resource;

        return [
            'id' => $category->getKey(),
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_path' => PublicMedia::url($category->image_path),
            'products_count' => $category->products_count !== null ? (int) $category->products_count : null,
        ];
    }
}
