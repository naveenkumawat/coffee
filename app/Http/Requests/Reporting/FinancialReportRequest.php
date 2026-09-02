<?php

namespace App\Http\Requests\Reporting;

use App\Http\Requests\AbstractRequest;
use App\Services\Reporting\FinancialReportingService;
use Illuminate\Validation\Rule;

class FinancialReportRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canViewFinancialReports() ?? false;
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in([
                FinancialReportingService::PRESET_TODAY,
                FinancialReportingService::PRESET_YESTERDAY,
                FinancialReportingService::PRESET_LAST_7_DAYS,
                FinancialReportingService::PRESET_THIS_MONTH,
                FinancialReportingService::PRESET_CUSTOM,
            ])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'channel' => ['nullable', 'string', Rule::in([
                FinancialReportingService::CHANNEL_ALL,
                FinancialReportingService::CHANNEL_TAKEAWAY,
                FinancialReportingService::CHANNEL_DELIVERY,
                FinancialReportingService::CHANNEL_DINING,
            ])],
            'payment_method' => ['nullable', 'string', Rule::in([
                FinancialReportingService::PAYMENT_ALL,
                FinancialReportingService::PAYMENT_CASH,
                FinancialReportingService::PAYMENT_UPI,
            ])],
        ];
    }
}
