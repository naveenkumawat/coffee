<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyProductRatingResource extends JsonResource
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
            'created_at' => optional($rating->created_at)?->toIso8601String(),
            'updated_at' => optional($rating->updated_at)?->toIso8601String(),
        ];
    }
}
