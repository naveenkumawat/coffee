<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CartItem;
use App\Support\PublicMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartItem $item */
        $item = $this->resource;
        $variant = $item->productVariant;
        $product = $variant?->product;
        $unitPrice = $variant?->price;
        $lineTotal = $variant
            ? bcmul((string) $variant->price, (string) $item->quantity, 2)
            : null;

        return [
            'id' => $item->getKey(),
            'quantity' => (int) $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'is_available' => (bool) (
                $variant?->is_active
                && $variant?->is_available
                && $product?->is_active
                && $product?->is_available
            ),
            'product' => $product ? [
                'id' => $product->getKey(),
                'name' => $product->name,
                'slug' => $product->slug,
                'short_description' => $product->short_description,
                'customer_ingredient_summary' => $product->customer_ingredient_summary,
                'image_path' => PublicMedia::url($product->image_path),
            ] : null,
            'variant' => $variant ? [
                'id' => $variant->getKey(),
                'name' => $variant->name,
                'serving_size_value' => $variant->serving_size_value,
                'serving_size_unit' => $variant->serving_size_unit?->value,
                'price' => $variant->price,
            ] : null,
        ];
    }
}
