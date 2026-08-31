<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HomeSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var HomeSection $section */
        $section = $this->resource;

        return [
            'id' => $section->getKey(),
            'title' => $section->title,
            'subtitle' => $section->subtitle,
            'slug' => $section->slug,
            'products' => ProductResource::collection($section->products)->resolve(),
        ];
    }
}
