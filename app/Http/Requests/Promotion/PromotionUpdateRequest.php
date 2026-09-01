<?php

namespace App\Http\Requests\Promotion;

use App\Models\Promotion;

class PromotionUpdateRequest extends PromotionStoreRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Promotion|null $promotion */
        $promotion = $this->route('promotion');

        return $this->promotionRules($promotion?->getKey());
    }
}
