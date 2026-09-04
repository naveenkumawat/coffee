<?php

namespace App\Http\Requests\Product;

use App\Enums\IngredientUnit;
use App\Enums\PreparationStation;
use App\Enums\ProductServingUnit;
use App\Enums\ProductType;
use App\Http\Requests\AbstractRequest;
use App\Support\PublicMedia;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductCreateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->filter(function ($row): bool {
                if (! is_array($row)) {
                    return false;
                }

                return filled($row['id'] ?? null)
                    || filled($row['name'] ?? null)
                    || filled($row['price'] ?? null)
                    || filled($row['serving_size_value'] ?? null);
            })
            ->values()
            ->all();

        $addOns = collect($this->input('add_ons', []))
            ->filter(fn ($row): bool => is_array($row) && filled($row['add_on_id'] ?? null))
            ->map(function (array $row): array {
                $row['lines'] = collect($row['lines'] ?? [])
                    ->filter(fn ($line): bool => is_array($line) && filled($line['ingredient_id'] ?? null))
                    ->values()
                    ->all();

                return $row;
            })
            ->values()
            ->all();

        $this->merge([
            'variants' => $variants,
            'add_ons' => $addOns,
        ]);
    }

    public function rules(): array
    {
        return [
            'product_category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->whereNull('deleted_at')],
            'product_type' => ['required', 'string', Rule::enum(ProductType::class)],
            'preparation_station' => ['required', 'string', Rule::enum(PreparationStation::class)],
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->whereNull('deleted_at')],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'customer_ingredient_summary' => ['nullable', 'string', 'max:255'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'image' => PublicMedia::uploadRules(),
            'remove_image' => ['nullable', 'boolean'],
            'preparation_time_minutes' => ['nullable', 'integer', 'min:0', 'max:999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'product_flavour_ids' => ['nullable', 'array'],
            'product_flavour_ids.*' => ['integer', Rule::exists('product_flavours', 'id')->whereNull('deleted_at')],
            'product_tag_ids' => ['nullable', 'array'],
            'product_tag_ids.*' => ['integer', Rule::exists('product_tags', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'is_active' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'is_vegetarian' => ['nullable', 'boolean'],
            'is_customizable' => ['nullable', 'boolean'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:120'],
            'variants.*.serving_size_value' => ['required', 'decimal:0,3', 'gt:0'],
            'variants.*.serving_size_unit' => ['required', 'string', Rule::in(array_keys(ProductServingUnit::options()))],
            'variants.*.price' => ['required', 'decimal:0,2', 'gte:0'],
            'variants.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'variants.*.is_active' => ['nullable', 'boolean'],
            'variants.*.is_available' => ['nullable', 'boolean'],
            'add_ons' => ['nullable', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', Rule::exists('add_ons', 'id')->whereNull('deleted_at'), 'distinct'],
            'add_ons.*.price_override' => ['nullable', 'decimal:0,2', 'gte:0'],
            'add_ons.*.max_quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'add_ons.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'add_ons.*.is_active' => ['nullable', 'boolean'],
            'add_ons.*.lines' => ['nullable', 'array'],
            'add_ons.*.lines.*.id' => ['nullable', 'integer'],
            'add_ons.*.lines.*.ingredient_id' => ['required', 'integer', Rule::exists('ingredients', 'id')->whereNull('deleted_at')],
            'add_ons.*.lines.*.quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'add_ons.*.lines.*.measurement_unit' => ['required', 'string', Rule::in(array_keys(IngredientUnit::options()))],
            'add_ons.*.lines.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $names = collect($this->input('variants', []))
                ->map(fn ($row) => is_array($row) ? Str::lower(trim((string) ($row['name'] ?? ''))) : '')
                ->filter()
                ->values();

            if ($names->count() !== $names->unique()->count()) {
                $validator->errors()->add('variants', 'Variant names must be unique for this product.');
            }
        });
    }
}
