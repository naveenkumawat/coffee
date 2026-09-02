<?php

namespace App\Http\Requests\Reporting;

use App\Enums\PreparationStation;
use App\Enums\ProductType;
use App\Http\Requests\AbstractRequest;
use App\Services\Reporting\OperationalPerformanceReportingService;
use Illuminate\Validation\Rule;

class OperationalPerformanceReportRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewFinancialReports() ?? false;
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in([
                OperationalPerformanceReportingService::PRESET_TODAY,
                OperationalPerformanceReportingService::PRESET_YESTERDAY,
                OperationalPerformanceReportingService::PRESET_LAST_7_DAYS,
                OperationalPerformanceReportingService::PRESET_THIS_MONTH,
                OperationalPerformanceReportingService::PRESET_CUSTOM,
            ])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'section' => ['nullable', 'string', Rule::in([
                OperationalPerformanceReportingService::SECTION_OVERVIEW,
                OperationalPerformanceReportingService::SECTION_BAR,
                OperationalPerformanceReportingService::SECTION_KITCHEN,
                OperationalPerformanceReportingService::SECTION_MIXED,
                OperationalPerformanceReportingService::SECTION_DINING,
                OperationalPerformanceReportingService::SECTION_LONG_RUNNING,
                OperationalPerformanceReportingService::SECTION_PRODUCTS,
            ])],
            'station' => ['nullable', 'string', Rule::in(array_keys(PreparationStation::options()))],
            'channel' => ['nullable', 'string', Rule::in([
                OperationalPerformanceReportingService::CHANNEL_ALL,
                OperationalPerformanceReportingService::CHANNEL_TAKEAWAY,
                OperationalPerformanceReportingService::CHANNEL_DELIVERY,
                OperationalPerformanceReportingService::CHANNEL_DINING,
            ])],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'product_type' => ['nullable', 'string', Rule::in(array_keys(ProductType::options()))],
        ];
    }
}
