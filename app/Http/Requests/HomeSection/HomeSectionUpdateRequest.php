<?php

namespace App\Http\Requests\HomeSection;

use App\Enums\HomeSectionPlacement;
use App\Enums\HomeSectionSourceType;
use App\Enums\RecommendationContext;
use App\Http\Requests\AbstractRequest;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HomeSectionUpdateRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageProducts() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placement' => $this->input('placement', 'home'),
            'source_type' => $this->input('source_type', 'curated'),
            'targeting_rules' => $this->decodeJsonField('targeting_rules'),
            'dedupe_products' => $this->has('dedupe_products') ? $this->boolean('dedupe_products') : true,
            'fallback_to_curated' => $this->has('fallback_to_curated') ? $this->boolean('fallback_to_curated') : true,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'max_items' => ['nullable', 'integer', 'min:1', 'max:50'],
            'placement' => ['required', 'string', Rule::in(HomeSectionPlacement::values())],
            'source_type' => ['required', 'string', Rule::in(HomeSectionSourceType::values())],
            'source_category_id' => ['nullable', 'integer', 'min:1', 'exists:product_categories,id'],
            'source_tag_id' => ['nullable', 'integer', 'min:1', 'exists:product_tags,id'],
            'recommendation_context' => ['nullable', 'string', Rule::in(RecommendationContext::values())],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'targeting_rules' => ['nullable', 'array'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'dedupe_products' => ['nullable', 'boolean'],
            'fallback_to_curated' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $source = (string) $this->input('source_type');

            if ($source === HomeSectionSourceType::Category->value && ! $this->filled('source_category_id')) {
                $validator->errors()->add('source_category_id', 'A category is required for category sections.');
            }

            if ($source === HomeSectionSourceType::Tag->value && ! $this->filled('source_tag_id')) {
                $validator->errors()->add('source_tag_id', 'A tag is required for tagged sections.');
            }

            try {
                $normalized = app(TargetingRuleValidator::class)->validateRuleGroups(
                    is_array($this->input('targeting_rules')) ? $this->input('targeting_rules') : [],
                    app(TargetingRuleValidator::class)->campaignRuleTypes(),
                    'targeting_rules',
                );
                $this->merge(['targeting_rules' => $normalized]);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonField(string $key): array
    {
        $value = $this->input($key);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['all' => [], 'any' => [], 'exclude' => []];
    }
}
