<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\AbstractRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'product_category_ids' => ['nullable', 'array', 'max:50'],
            'product_category_ids.*' => ['integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'product_flavour_id' => ['nullable', 'integer', Rule::exists('product_flavours', 'id')->whereNull('deleted_at')],
            'product_flavour_ids' => ['nullable', 'array', 'max:50'],
            'product_flavour_ids.*' => ['integer', Rule::exists('product_flavours', 'id')->whereNull('deleted_at')],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'availability' => ['nullable', 'string', Rule::in(['available', 'unavailable'])],
            'featured' => ['nullable', 'string', Rule::in(['featured', 'standard'])],
            'new' => ['nullable', 'string', Rule::in(['new'])],
            'bestseller' => ['nullable', 'string', Rule::in(['bestseller'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogFilters(): array
    {
        $validated = $this->validated();

        $categoryIds = collect($validated['product_category_ids'] ?? [])
            ->when(filled($validated['product_category_id'] ?? null), fn ($ids) => $ids->push($validated['product_category_id']))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $flavourIds = collect($validated['product_flavour_ids'] ?? [])
            ->when(filled($validated['product_flavour_id'] ?? null), fn ($ids) => $ids->push($validated['product_flavour_id']))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        unset($validated['product_category_id'], $validated['product_category_ids'], $validated['product_flavour_id'], $validated['product_flavour_ids']);

        if ($categoryIds !== []) {
            $validated['product_category_ids'] = $categoryIds;
        }

        if ($flavourIds !== []) {
            $validated['product_flavour_ids'] = $flavourIds;
        }

        return $validated;
    }
}
