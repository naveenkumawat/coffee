<?php

namespace App\Http\Requests\Reporting;

use App\Http\Requests\AbstractRequest;
use App\Services\Attribution\AttributionAnalyticsService;
use Illuminate\Validation\Rule;

class AttributionAnalyticsRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewFinancialReports() ?? false;
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in([
                AttributionAnalyticsService::PRESET_TODAY,
                AttributionAnalyticsService::PRESET_YESTERDAY,
                AttributionAnalyticsService::PRESET_LAST_7_DAYS,
                AttributionAnalyticsService::PRESET_THIS_MONTH,
                AttributionAnalyticsService::PRESET_CUSTOM,
            ])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
        ];
    }
}
