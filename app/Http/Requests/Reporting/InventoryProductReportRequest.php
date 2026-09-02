<?php

namespace App\Http\Requests\Reporting;

use App\Enums\InventoryStockStatus;
use App\Enums\PreparationStation;
use App\Enums\ProductType;
use App\Http\Requests\AbstractRequest;
use App\Services\Reporting\InventoryProductReportingService;
use Illuminate\Validation\Rule;

class InventoryProductReportRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewFinancialReports() ?? false;
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in([
                InventoryProductReportingService::PRESET_TODAY,
                InventoryProductReportingService::PRESET_YESTERDAY,
                InventoryProductReportingService::PRESET_LAST_7_DAYS,
                InventoryProductReportingService::PRESET_THIS_MONTH,
                InventoryProductReportingService::PRESET_CUSTOM,
            ])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'section' => ['nullable', 'string', Rule::in([
                InventoryProductReportingService::SECTION_OVERVIEW,
                InventoryProductReportingService::SECTION_INGREDIENTS,
                InventoryProductReportingService::SECTION_PRODUCTS,
                InventoryProductReportingService::SECTION_REFILLS,
                InventoryProductReportingService::SECTION_MOVEMENTS,
            ])],
            'ingredient_id' => ['nullable', 'integer', 'exists:ingredients,id'],
            'ingredient_category_id' => ['nullable', 'integer', 'exists:ingredient_categories,id'],
            'stock_status' => ['nullable', 'string', Rule::in(array_keys(InventoryStockStatus::options()))],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'product_type' => ['nullable', 'string', Rule::in(array_keys(ProductType::options()))],
            'station' => ['nullable', 'string', Rule::in(array_keys(PreparationStation::options()))],
        ];
    }
}
