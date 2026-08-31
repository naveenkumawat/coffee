<?php

namespace App\Services\WebsiteSetting;

use App\Enums\WebsiteSettingKey;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
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
                'dine_in_enabled' => $this->dineInEnabled(),
            ],
            'pages' => [
                'about' => $this->filledOrNull($values[WebsiteSettingKey::PagesAbout->value] ?? null),
                'contact' => $this->filledOrNull($values[WebsiteSettingKey::PagesContact->value] ?? null),
                'faq' => $this->filledOrNull($values[WebsiteSettingKey::PagesFaq->value] ?? null),
                'terms' => $this->filledOrNull($values[WebsiteSettingKey::PagesTerms->value] ?? null),
                'privacy' => $this->filledOrNull($values[WebsiteSettingKey::PagesPrivacy->value] ?? null),
            ],
            'social_links' => $this->socialLinks->customerFacingLinks($businessWhatsapp),
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
}
