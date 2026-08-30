<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductFlavour;
use App\Support\PublicMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductFlavourResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductFlavour $flavour */
        $flavour = $this->resource;

        return [
            'id' => $flavour->getKey(),
            'name' => $flavour->name,
            'slug' => $flavour->slug,
            'description' => $flavour->description,
            'image_path' => PublicMedia::url($flavour->image_path),
            'products_count' => $flavour->products_count !== null ? (int) $flavour->products_count : null,
            'categories' => ProductCategoryResource::collection($flavour->categories),
        ];
    }
}
