<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrderItem $item */
        $item = $this->resource;

        return [
            'id' => $item->getKey(),
            'product_id' => $item->product_id,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product_name,
            'variant_name' => $item->variant_name,
            'customer_ingredient_summary' => $item->customer_ingredient_summary,
            'unit_price' => $item->unit_price,
            'quantity' => (int) $item->quantity,
            'line_subtotal' => $item->line_subtotal,
            'my_rating' => $this->when(
                $item->relationLoaded('myRating'),
                function () use ($item) {
                    $rating = $item->getRelation('myRating');

                    return $rating ? (new MyProductRatingResource($rating))->resolve() : null;
                },
            ),
            'can_rate' => $this->when(
                array_key_exists('can_rate', $item->getAttributes()),
                fn () => (bool) $item->getAttribute('can_rate'),
            ),
        ];
    }
}
