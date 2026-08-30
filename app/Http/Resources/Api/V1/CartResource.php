<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Cart $cart */
        $cart = $this->resource;

        return [
            'id' => $cart->getKey(),
            'items' => CartItemResource::collection($cart->items),
            'created_at' => $cart->created_at?->toIso8601String(),
            'updated_at' => $cart->updated_at?->toIso8601String(),
        ];
    }
}
