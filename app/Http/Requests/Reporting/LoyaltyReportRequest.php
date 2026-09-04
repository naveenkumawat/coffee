<?php

namespace App\Http\Requests\Reporting;

use App\Enums\LoyaltyTransactionType;
use App\Http\Requests\AbstractRequest;
use App\Services\Loyalty\LoyaltyReportingService;
use Illuminate\Validation\Rule;

class LoyaltyReportRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->canManageWebsiteSettings() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', Rule::in([
                LoyaltyReportingService::PRESET_TODAY,
                LoyaltyReportingService::PRESET_YESTERDAY,
                LoyaltyReportingService::PRESET_LAST_7_DAYS,
                LoyaltyReportingService::PRESET_THIS_MONTH,
                LoyaltyReportingService::PRESET_CUSTOM,
            ])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_if:preset,custom', 'after_or_equal:from'],
            'transaction_type' => ['nullable', 'string', Rule::in([
                'all',
                LoyaltyTransactionType::Earn->value,
                LoyaltyTransactionType::Redeem->value,
                LoyaltyTransactionType::Adjustment->value,
                LoyaltyTransactionType::Reversal->value,
                'restore',
                'earn_reversal',
            ])],
            'reward_id' => ['nullable', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'balance_filter' => ['nullable', 'string', Rule::in(['all', 'debt', 'positive'])],
        ];
    }
}
