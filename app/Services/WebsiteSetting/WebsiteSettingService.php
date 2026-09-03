<?php

namespace App\Services\WebsiteSetting;

use App\Enums\WebsiteSettingKey;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\Social\SocialLinkServiceInterface;
use App\Support\PublicMedia;

class WebsiteSettingService implements WebsiteSettingServiceInterface
{
    public function __construct(
        protected WebsiteSettingRepositoryInterface $settings,
        protected SocialLinkServiceInterface $socialLinks,
    ) {}

    public function valuesForAdmin(): array
    {
        $stored = $this->settings->keyedValues();
        $values = [];

        foreach (WebsiteSettingKey::ordered() as $key) {
            $values[$key->value] = $this->sanitizePlainText($stored->get($key->value));
        }

        return $values;
    }

    public function update(array $input): void
    {
        $values = [];

        foreach (WebsiteSettingKey::ordered() as $key) {
            if (! array_key_exists($key->value, $input)) {
                continue;
            }

            $values[$key->value] = $this->normalizeStoredValue($input[$key->value] ?? null);
        }

        $this->settings->upsertValues($values);

        if (array_key_exists(WebsiteSettingKey::BusinessTimezone->value, $values)
            || array_key_exists(WebsiteSettingKey::OrderingManualClosed->value, $values)
            || array_key_exists(WebsiteSettingKey::OrderingManualClosedUntil->value, $values)
            || array_key_exists(WebsiteSettingKey::OrderingManualClosedMessage->value, $values)
        ) {
            app(CafeAvailabilityServiceInterface::class)->flushAvailabilityCache();
        }
    }

    public function customerContent(): array
    {
        $values = $this->valuesForAdmin();
        $payment = $this->paymentInstructions();
        $businessWhatsapp = $this->filledOrNull($values[WebsiteSettingKey::BusinessWhatsappNumber->value] ?? null)
            ?? $payment['whatsapp_number'];

        return [
            'hero' => [
                'title' => $this->filledOrNull($values[WebsiteSettingKey::HeroTitle->value] ?? null),
                'subtitle' => $this->filledOrNull($values[WebsiteSettingKey::HeroSubtitle->value] ?? null),
                'image_path' => PublicMedia::url($this->filledOrNull($values[WebsiteSettingKey::HeroImagePath->value] ?? null)),
            ],
            'business' => [
                'name' => $this->filledOrNull($values[WebsiteSettingKey::BusinessName->value] ?? null)
                    ?? $this->filledOrNull((string) config('coffee.company.name')),
                'about_short' => $this->filledOrNull($values[WebsiteSettingKey::BusinessAboutShort->value] ?? null),
                'phone' => $this->filledOrNull($values[WebsiteSettingKey::BusinessPhone->value] ?? null),
                'whatsapp_number' => $businessWhatsapp,
                'email' => $this->filledOrNull($values[WebsiteSettingKey::BusinessEmail->value] ?? null)
                    ?? $this->filledOrNull((string) config('coffee.company.support_email')),
                'address' => $this->filledOrNull($values[WebsiteSettingKey::BusinessAddress->value] ?? null),
                'opening_hours' => $this->filledOrNull($values[WebsiteSettingKey::BusinessOpeningHours->value] ?? null),
            ],
            'payment' => $payment,
            'fulfilment' => [
                'delivery_disclaimer' => $this->deliveryDisclaimer(),
                'dine_in_enabled' => $this->diningEnabled(),
                'dining_enabled' => $this->diningEnabled(),
            ],
            'behaviour' => [
                'tracking_enabled' => (bool) config('coffee.behaviour.enabled', true),
            ],
            'pages' => [
                'about' => $this->filledOrNull($values[WebsiteSettingKey::PagesAbout->value] ?? null),
                'contact' => $this->filledOrNull($values[WebsiteSettingKey::PagesContact->value] ?? null),
                'faq' => $this->filledOrNull($values[WebsiteSettingKey::PagesFaq->value] ?? null),
                'terms' => $this->filledOrNull($values[WebsiteSettingKey::PagesTerms->value] ?? null),
                'privacy' => $this->filledOrNull($values[WebsiteSettingKey::PagesPrivacy->value] ?? null),
            ],
            'social_links' => $this->socialLinks->customerFacingLinks($businessWhatsapp),
            'availability' => app(CafeAvailabilityServiceInterface::class)->publicStatus()->toPublicArray(),
        ];
    }

    public function paymentInstructions(): array
    {
        $values = $this->settings->keyedValues();

        return [
            'display_name' => $this->resolveWithConfigFallback(
                $values->get(WebsiteSettingKey::PaymentDisplayName->value),
                config('coffee.payments.display_name'),
            ),
            'instructions' => $this->resolveWithConfigFallback(
                $values->get(WebsiteSettingKey::PaymentInstructions->value),
                config('coffee.payments.instructions'),
            ),
            'upi_id' => $this->resolveWithConfigFallback(
                $values->get(WebsiteSettingKey::PaymentUpiId->value),
                config('coffee.payments.upi_id'),
            ),
            'phone' => $this->resolveWithConfigFallback(
                $values->get(WebsiteSettingKey::PaymentPhone->value),
                config('coffee.payments.phone'),
            ),
            'qr_image_path' => PublicMedia::url($this->resolveWithConfigFallback(
                $values->get(WebsiteSettingKey::PaymentQrImagePath->value),
                config('coffee.payments.qr_image_path'),
            )),
            'whatsapp_number' => $this->resolveWithConfigFallback(
                $values->get(WebsiteSettingKey::PaymentWhatsappNumber->value),
                config('coffee.payments.whatsapp_number'),
            ),
        ];
    }

    public function deliveryDisclaimer(): ?string
    {
        $values = $this->settings->keyedValues();

        return $this->resolveWithConfigFallback(
            $values->get(WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value),
            config('coffee.fulfilment.delivery_disclaimer'),
        );
    }

    public function dineInEnabled(): bool
    {
        $values = $this->settings->keyedValues();
        $raw = $values->get(WebsiteSettingKey::FulfilmentDineInEnabled->value);

        return in_array(strtolower(trim((string) ($raw ?? ''))), ['1', 'true', 'yes', 'on'], true);
    }

    public function diningEnabled(): bool
    {
        return $this->dineInEnabled();
    }

    public function orderSecurityConfig(): array
    {
        $values = $this->settings->keyedValues();

        return [
            'enabled' => $this->toBoolSetting($values->get(WebsiteSettingKey::OrderSecurityEnabled->value), true),
            'max_open_unpaid_orders' => $this->toIntSetting($values->get(WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders->value), 2, 1, 20),
            'max_orders_per_hour' => $this->toIntSetting($values->get(WebsiteSettingKey::OrderSecurityMaxOrdersPerHour->value), 5, 1, 60),
            'checkout_attempts_per_10_minutes' => $this->toIntSetting($values->get(WebsiteSettingKey::OrderSecurityCheckoutAttemptsPer10Minutes->value), 5, 1, 60),
            'payment_proof_attempts_per_15_minutes' => $this->toIntSetting($values->get(WebsiteSettingKey::OrderSecurityPaymentProofAttemptsPer15Minutes->value), 5, 1, 60),
            'duplicate_order_window_minutes' => $this->toIntSetting($values->get(WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes->value), 3, 1, 30),
        ];
    }

    public function taxConfig(): array
    {
        $values = $this->settings->keyedValues();
        $enabled = in_array(
            strtolower(trim((string) ($values->get(WebsiteSettingKey::TaxEnabled->value) ?? ''))),
            ['1', 'true', 'yes', 'on'],
            true,
        );
        $inclusive = in_array(
            strtolower(trim((string) ($values->get(WebsiteSettingKey::TaxInclusive->value) ?? ''))),
            ['1', 'true', 'yes', 'on'],
            true,
        );
        $label = $this->filledOrNull($values->get(WebsiteSettingKey::TaxLabel->value)) ?: 'GST';
        $percentRaw = $this->filledOrNull($values->get(WebsiteSettingKey::TaxPercent->value)) ?: '0.00';
        $percent = is_numeric($percentRaw) ? number_format((float) $percentRaw, 2, '.', '') : '0.00';

        return [
            'enabled' => $enabled,
            'label' => $label,
            'percent' => $percent,
            'inclusive' => $inclusive,
            'gstin' => $this->filledOrNull($values->get(WebsiteSettingKey::TaxGstin->value)),
            'legal_business_name' => $this->filledOrNull($values->get(WebsiteSettingKey::TaxLegalBusinessName->value)),
        ];
    }

    public function referralConfig(): array
    {
        $values = $this->settings->keyedValues();
        $enabled = in_array(
            strtolower(trim((string) ($values->get(WebsiteSettingKey::ReferralEnabled->value) ?? ''))),
            ['1', 'true', 'yes', 'on'],
            true,
        );

        $rewardType = trim((string) ($values->get(WebsiteSettingKey::ReferralRewardType->value) ?? 'free_drink'));
        if (! in_array($rewardType, ['free_drink', 'coupon'], true)) {
            $rewardType = 'free_drink';
        }

        $couponType = trim((string) ($values->get(WebsiteSettingKey::ReferralCouponDiscountType->value) ?? 'fixed'));
        if (! in_array($couponType, ['fixed', 'percentage'], true)) {
            $couponType = 'fixed';
        }

        $duration = $this->toIntSetting($values->get(WebsiteSettingKey::ReferralRewardRedemptionDurationDays->value), 30, 1, 3650);
        $maxMonthRaw = $values->get(WebsiteSettingKey::ReferralMaxRewardsPerCustomerMonth->value);

        return [
            'enabled' => $enabled,
            'reward_type' => $rewardType,
            'reward_product_id' => $this->nullablePositiveInt($values->get(WebsiteSettingKey::ReferralRewardProductId->value)),
            'reward_variant_id' => $this->nullablePositiveInt($values->get(WebsiteSettingKey::ReferralRewardVariantId->value)),
            'reward_quantity' => $this->toIntSetting($values->get(WebsiteSettingKey::ReferralRewardQuantity->value), 1, 1, 20),
            'coupon_discount_type' => $couponType,
            'coupon_discount_value' => $this->moneySetting($values->get(WebsiteSettingKey::ReferralCouponDiscountValue->value), '0.00'),
            'coupon_max_discount' => $this->nullableMoneySetting($values->get(WebsiteSettingKey::ReferralCouponMaxDiscount->value)),
            'coupon_minimum_subtotal' => $this->nullableMoneySetting($values->get(WebsiteSettingKey::ReferralCouponMinimumSubtotal->value)),
            'minimum_qualifying_order_amount' => $this->nullableMoneySetting($values->get(WebsiteSettingKey::ReferralMinimumQualifyingOrderAmount->value)),
            'reward_redemption_duration_days' => $duration,
            'max_rewards_per_customer_month' => ($maxMonthRaw === null || $maxMonthRaw === '')
                ? null
                : $this->toIntSetting($maxMonthRaw, 10, 1, 1000),
        ];
    }

    protected function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    protected function moneySetting(mixed $value, string $default): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return $default;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function nullableMoneySetting(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function normalizeStoredValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $normalized = $this->sanitizePlainText(is_string($value) ? $value : (string) $value);

        return $normalized === '' ? null : $normalized;
    }

    protected function sanitizePlainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stripped = preg_replace('#<\s*(script|style)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $value) ?? '';
        $stripped = strip_tags($stripped);
        $stripped = str_replace(["\0", "\r"], '', $stripped);

        return trim($stripped);
    }

    protected function filledOrNull(?string $value): ?string
    {
        $sanitized = $this->sanitizePlainText($value);

        return ($sanitized === null || $sanitized === '') ? null : $sanitized;
    }

    protected function resolveWithConfigFallback(mixed $settingValue, mixed $configValue): ?string
    {
        $fromSetting = $this->filledOrNull(is_string($settingValue) ? $settingValue : (filled($settingValue) ? (string) $settingValue : null));

        if ($fromSetting !== null) {
            return $fromSetting;
        }

        return $this->filledOrNull(is_string($configValue) ? $configValue : (filled($configValue) ? (string) $configValue : null));
    }

    protected function toBoolSetting(mixed $raw, bool $default): bool
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function toIntSetting(mixed $raw, int $default, int $min, int $max): int
    {
        if (! is_numeric($raw)) {
            return $default;
        }

        return max($min, min($max, (int) $raw));
    }
}
