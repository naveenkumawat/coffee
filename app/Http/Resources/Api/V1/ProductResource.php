<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Support\PublicMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->getKey(),
            'category' => $product->category ? new ProductCategoryResource($product->category) : null,
            'flavours' => ProductFlavourResource::collection($product->flavours),
            'tags' => ProductTagResource::collection($product->relationLoaded('tags')
                ? $product->tags
                : $product->tags()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()),
            'default_variant' => $product->defaultVariant ? new ProductVariantResource($product->defaultVariant) : null,
            'variants' => ProductVariantResource::collection($product->variants),
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'customer_ingredient_summary' => $product->customer_ingredient_summary,
            'image_path' => PublicMedia::url($product->image_path),
            'preparation_time_minutes' => $product->preparation_time_minutes,
            'is_featured' => (bool) $product->is_featured,
            'is_new' => (bool) $product->is_new,
            'is_bestseller' => (bool) $product->is_bestseller,
            'is_vegetarian' => (bool) $product->is_vegetarian,
            'is_customizable' => (bool) $product->is_customizable,
            'rating_summary' => $this->ratingSummary($product),
            'my_rating' => $this->when(
                $product->relationLoaded('myRating'),
                function () use ($product) {
                    $rating = $product->getRelation('myRating');

                    return $rating ? (new MyProductRatingResource($rating))->resolve() : null;
                },
            ),
            'can_rate' => $this->when(
                array_key_exists('can_rate', $product->getAttributes()),
                fn () => (bool) $product->getAttribute('can_rate'),
            ),
        ];
    }

    /**
     * @return array{average: float|null, count: int}
     */
    protected function ratingSummary(Product $product): array
    {
        $count = (int) ($product->ratings_count ?? 0);
        $average = null;

        if ($count > 0 && $product->ratings_avg_rating !== null) {
            $average = round((float) $product->ratings_avg_rating, 1);
        }

        return [
            'average' => $average,
            'count' => $count,
        ];
    }
}
