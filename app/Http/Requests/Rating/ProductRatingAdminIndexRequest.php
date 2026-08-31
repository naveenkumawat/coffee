<?php

namespace App\Http\Requests\Rating;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class ProductRatingAdminIndexRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_public' => ['nullable', 'string', Rule::in(['1', '0', 'true', 'false'])],
        ];
    }

    /**
     * @return array{search?: string|null, product_id?: int|null, rating?: int|null, is_public?: bool|null}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        $isPublic = null;
        if (array_key_exists('is_public', $validated) && $validated['is_public'] !== null && $validated['is_public'] !== '') {
            $isPublic = filter_var($validated['is_public'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'search' => $validated['search'] ?? null,
            'product_id' => isset($validated['product_id']) ? (int) $validated['product_id'] : null,
            'rating' => isset($validated['rating']) ? (int) $validated['rating'] : null,
            'is_public' => $isPublic,
        ];
    }
}
