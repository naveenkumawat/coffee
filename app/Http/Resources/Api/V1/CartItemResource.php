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
        $baseUnitPrice = $variant?->price;
        $addonLine = '0.00';
        $addOnPayload = [];
        foreach ($item->relationLoaded('addOns') ? $item->addOns : $item->addOns()->with('addOn')->get() as $cartAddOn) {
            $unit = bcdiv((string) $cartAddOn->unit_price, '1', 2);
            $line = bcmul($unit, (string) ((int) $cartAddOn->quantity * (int) $item->quantity), 2);
            $addonLine = bcadd($addonLine, $line, 2);
            $addOnPayload[] = [
                'add_on_id' => (int) $cartAddOn->add_on_id,
                'name' => $cartAddOn->addOn?->name,
                'quantity' => (int) $cartAddOn->quantity,
                'unit_price' => $unit,
                'line_total' => bcmul($unit, (string) $cartAddOn->quantity, 2),
            ];
        }
        $baseLine = $variant ? bcmul((string) $variant->price, (string) $item->quantity, 2) : null;
        $lineTotal = $baseLine !== null ? bcadd($baseLine, $addonLine, 2) : null;
        $unitPrice = $lineTotal !== null && (int) $item->quantity > 0
            ? bcdiv($lineTotal, (string) $item->quantity, 2)
            : $baseUnitPrice;

        return [
            'id' => $item->getKey(),
            'quantity' => (int) $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'base_unit_price' => $baseUnitPrice,
            'base_line_total' => $baseLine,
            'addon_line_total' => $addonLine,
            'add_ons' => $addOnPayload,
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
