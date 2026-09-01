<?php

namespace App\Http\Requests\WebsiteSetting;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use App\Support\PublicMedia;
use Illuminate\Foundation\Http\FormRequest;

class WebsiteSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('update', WebsiteSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'hero_image' => PublicMedia::uploadRules(),
            'payment_qr_image' => PublicMedia::uploadRules(),
            'remove_hero_image' => ['nullable', 'boolean'],
            'remove_payment_qr_image' => ['nullable', 'boolean'],
        ];

        foreach (WebsiteSettingKey::ordered() as $key) {
            if ($key->valueType() === 'boolean') {
                $rules[$key->value] = ['nullable', 'boolean'];

                continue;
            }

            if ($key->valueType() === 'integer') {
                $rules[$key->value] = match ($key) {
                    WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders => ['nullable', 'integer', 'min:1', 'max:20'],
                    WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes => ['nullable', 'integer', 'min:1', 'max:30'],
                    default => ['nullable', 'integer', 'min:1', 'max:60'],
                };

                continue;
            }

            if ($key === WebsiteSettingKey::BusinessTimezone) {
                $rules[$key->value] = ['nullable', 'string', 'timezone:all'];

                continue;
            }

            if ($key === WebsiteSettingKey::TaxPercent) {
                $rules[$key->value] = [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100',
                    'regex:/^\d{1,3}(\.\d{1,2})?$/',
                    'required_if:tax_enabled,1,true',
                ];

                continue;
            }

            $fieldRules = ['nullable', 'string', 'max:'.$key->maxLength()];

            if ($key === WebsiteSettingKey::BusinessEmail) {
                $fieldRules[] = 'email';
            }

            $rules[$key->value] = $fieldRules;
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        foreach (WebsiteSettingKey::ordered() as $key) {
            if ($key->valueType() !== 'boolean') {
                continue;
            }

            $this->merge([
                $key->value => $this->boolean($key->value),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'hero_image' => 'Hero image',
            'payment_qr_image' => 'Payment QR image',
            'remove_hero_image' => 'Remove hero image',
            'remove_payment_qr_image' => 'Remove payment QR image',
        ];

        foreach (WebsiteSettingKey::ordered() as $key) {
            $attributes[$key->value] = $key->label();
        }

        return $attributes;
    }
}
