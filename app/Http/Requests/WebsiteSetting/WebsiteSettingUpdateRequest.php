<?php

namespace App\Http\Requests\WebsiteSetting;

use App\Enums\BrandDisplayMode;
use App\Enums\WebsiteSettingKey;
use App\Enums\WebsiteSettingSection;
use App\Models\WebsiteSetting;
use App\Rules\BrandLogoUpload;
use App\Support\PublicMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebsiteSettingUpdateRequest extends FormRequest
{
    protected string $incomingSection = '';

    public function authorize(): bool
    {
        return $this->user('admin')?->can('update', WebsiteSetting::class) ?? false;
    }

    public function incomingSection(): string
    {
        return $this->incomingSection;
    }

    public function section(): WebsiteSettingSection
    {
        return WebsiteSettingSection::fromQuery($this->input('section', $this->query('section')));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (in_array($this->incomingSection, ['hero', 'social'], true)) {
            return [];
        }

        $section = $this->section();
        $rules = [
            'section' => ['required', Rule::enum(WebsiteSettingSection::class)],
        ];

        if ($section === WebsiteSettingSection::Branding) {
            $rules['brand_logo'] = ['nullable', 'file', new BrandLogoUpload];
            $rules['remove_brand_logo'] = ['nullable', 'boolean'];
        }

        if ($section === WebsiteSettingSection::Payments) {
            $rules['payment_qr_image'] = PublicMedia::uploadRules();
            $rules['remove_payment_qr_image'] = ['nullable', 'boolean'];
        }

        foreach ($section->settingKeys() as $key) {
            if (in_array($key, [
                WebsiteSettingKey::BrandLogoPath,
                WebsiteSettingKey::BrandFaviconPath,
                WebsiteSettingKey::PagesAbout,
                WebsiteSettingKey::PagesContact,
                WebsiteSettingKey::PagesFaq,
                WebsiteSettingKey::PagesTerms,
                WebsiteSettingKey::PagesPrivacy,
            ], true)) {
                continue;
            }

            if ($key === WebsiteSettingKey::BrandDisplayMode) {
                $rules[$key->value] = ['required', 'string', Rule::enum(BrandDisplayMode::class)];

                continue;
            }

            if ($key->valueType() === 'boolean') {
                $rules[$key->value] = ['nullable', 'boolean'];

                continue;
            }

            if ($key->valueType() === 'integer') {
                $rules[$key->value] = match ($key) {
                    WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders => ['nullable', 'integer', 'min:1', 'max:20'],
                    WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes => ['nullable', 'integer', 'min:1', 'max:30'],
                    WebsiteSettingKey::ReferralRewardQuantity => ['nullable', 'integer', 'min:1', 'max:20'],
                    WebsiteSettingKey::ReferralRewardRedemptionDurationDays => ['nullable', 'integer', 'min:1', 'max:3650'],
                    WebsiteSettingKey::ReferralMaxRewardsPerCustomerMonth => ['nullable', 'integer', 'min:1', 'max:1000'],
                    WebsiteSettingKey::ReferralRewardProductId,
                    WebsiteSettingKey::ReferralRewardVariantId => ['nullable', 'integer', 'min:1'],
                    default => ['nullable', 'integer', 'min:1', 'max:60'],
                };

                continue;
            }

            if ($key === WebsiteSettingKey::ReferralRewardType) {
                $rules[$key->value] = ['nullable', 'string', 'in:free_drink,coupon'];

                continue;
            }

            if ($key === WebsiteSettingKey::ReferralCouponDiscountType) {
                $rules[$key->value] = ['nullable', 'string', 'in:fixed,percentage'];

                continue;
            }

            if (in_array($key, [
                WebsiteSettingKey::ReferralCouponDiscountValue,
                WebsiteSettingKey::ReferralCouponMaxDiscount,
                WebsiteSettingKey::ReferralCouponMinimumSubtotal,
                WebsiteSettingKey::ReferralMinimumQualifyingOrderAmount,
            ], true)) {
                $rules[$key->value] = ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,6}(\.\d{1,2})?$/'];

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
        $this->incomingSection = trim((string) $this->input('section', $this->query('section')));

        if (in_array($this->incomingSection, ['hero', 'social'], true)) {
            return;
        }

        $section = WebsiteSettingSection::fromQuery($this->incomingSection);

        $this->merge([
            'section' => $section->value,
        ]);

        foreach ($section->settingKeys() as $key) {
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
            'section' => 'Settings category',
            'payment_qr_image' => 'Payment QR image',
            'brand_logo' => 'Primary logo',
            'remove_payment_qr_image' => 'Remove payment QR image',
            'remove_brand_logo' => 'Remove primary logo',
        ];

        foreach (WebsiteSettingKey::ordered() as $key) {
            $attributes[$key->value] = $key->label();
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxLabel = PublicMedia::brandLogoMaxMegabytesLabel();

        return [
            'brand_logo.file' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
            'brand_logo.max' => 'Primary logo must not be larger than '.$maxLabel.' MB.',
            'brand_logo.mimes' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
            'brand_logo.mimetypes' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
            'brand_logo.image' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
        ];
    }
}
