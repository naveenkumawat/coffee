<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ProductTagStyle;
use App\Models\ProductTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductTag $tag */
        $tag = $this->resource;
        $style = $tag->style_key instanceof ProductTagStyle
            ? $tag->style_key->value
            : (string) $tag->style_key;

        return [
            'key' => $tag->slug,
            'label' => $tag->name,
            'style' => $style,
        ];
    }
}
