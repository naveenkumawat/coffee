<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductRatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductRating $rating */
        $rating = $this->resource;

        return [
            'id' => $rating->getKey(),
            'rating' => (int) $rating->rating,
            'review' => $rating->review,
            'customer_display_name' => $rating->publicCustomerName(),
            'is_verified_purchase' => $rating->qualifying_order_id !== null,
            'created_at' => optional($rating->created_at)?->toIso8601String(),
            'updated_at' => optional($rating->updated_at)?->toIso8601String(),
        ];
    }
}
