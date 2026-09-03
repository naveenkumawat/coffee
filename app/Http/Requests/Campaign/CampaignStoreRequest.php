<?php

namespace App\Http\Requests\Campaign;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Http\Requests\AbstractRequest;
use App\Support\PublicMedia;
use Illuminate\Validation\Rule;

class CampaignStoreRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->canManageWebsiteSettings();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placement_rules' => $this->decodeJsonField('placement_rules'),
            'targeting_rules' => $this->decodeJsonField('targeting_rules'),
            'trigger_rules' => $this->decodeJsonField('trigger_rules'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'internal_label' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'string', Rule::in(CampaignStatus::values())],
            'surface' => ['required', 'string', Rule::in(CampaignSurface::values())],
            'title' => ['required', 'string', 'max:160'],
            'message' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'file', 'max:4096', 'mimes:'.implode(',', PublicMedia::allowedExtensions())],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_type' => ['required', 'string', Rule::in(CampaignCtaType::values())],
            'cta_product_id' => ['nullable', 'integer', 'min:1', 'exists:products,id'],
            'cta_category_id' => ['nullable', 'integer', 'min:1', 'exists:product_categories,id'],
            'cta_promotion_id' => ['nullable', 'integer', 'min:1', 'exists:promotions,id'],
            'cta_internal_path' => ['nullable', 'string', 'max:120'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'frequency_policy' => ['required', 'string', Rule::in(CampaignFrequencyPolicy::values())],
            'cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'max_impressions' => ['nullable', 'integer', 'min:1', 'max:100'],
            'placement_rules' => ['required', 'array'],
            'targeting_rules' => ['required', 'array'],
            'trigger_rules' => ['required', 'array'],
            'trigger_rules.type' => ['required', 'string', Rule::in(CampaignTriggerType::values())],
        ];
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

        return [];
    }
}
