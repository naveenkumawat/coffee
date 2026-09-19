<?php

namespace App\Enums;

enum WebsiteSettingSection: string
{
    case Branding = 'branding';
    case Business = 'business';
    case Ordering = 'ordering';
    case Payments = 'payments';
    case Dining = 'dining';
    case Tax = 'tax';
    case Award = 'award';

    public function label(): string
    {
        return match ($this) {
            self::Branding => 'Branding',
            self::Business => 'Business',
            self::Ordering => 'Ordering',
            self::Payments => 'Payments',
            self::Dining => 'Dining',
            self::Tax => 'Tax',
            self::Award => 'Award',
        };
    }

    /**
     * @return list<WebsiteSettingKey>
     */
    public function settingKeys(): array
    {
        return match ($this) {
            self::Branding => [
                WebsiteSettingKey::BusinessName,
                WebsiteSettingKey::HeroSubtitle,
                WebsiteSettingKey::BrandLogoPath,
                WebsiteSettingKey::BrandDisplayMode,
            ],
            self::Business => [
                WebsiteSettingKey::BusinessAboutShort,
                WebsiteSettingKey::BusinessPhone,
                WebsiteSettingKey::BusinessWhatsappNumber,
                WebsiteSettingKey::BusinessEmail,
                WebsiteSettingKey::BusinessAddress,
                WebsiteSettingKey::BusinessTimezone,
            ],
            self::Ordering => [
                WebsiteSettingKey::OrderSecurityEnabled,
                WebsiteSettingKey::OrderSecurityMaxOpenUnpaidOrders,
                WebsiteSettingKey::OrderSecurityMaxOrdersPerHour,
                WebsiteSettingKey::OrderSecurityCheckoutAttemptsPer10Minutes,
                WebsiteSettingKey::OrderSecurityPaymentProofAttemptsPer15Minutes,
                WebsiteSettingKey::OrderSecurityDuplicateOrderWindowMinutes,
                WebsiteSettingKey::OrderingManualClosed,
                WebsiteSettingKey::OrderingManualClosedUntil,
                WebsiteSettingKey::OrderingManualClosedMessage,
            ],
            self::Payments => [
                WebsiteSettingKey::PaymentDisplayName,
                WebsiteSettingKey::PaymentInstructions,
                WebsiteSettingKey::PaymentUpiId,
                WebsiteSettingKey::PaymentPhone,
                WebsiteSettingKey::PaymentQrImagePath,
                WebsiteSettingKey::PaymentWhatsappNumber,
                WebsiteSettingKey::PaymentCashEnabled,
                WebsiteSettingKey::PaymentManualUpiEnabled,
                WebsiteSettingKey::PaymentRazorpayEnabled,
                WebsiteSettingKey::PaymentPayuEnabled,
                WebsiteSettingKey::PaymentPaytmEnabled,
                WebsiteSettingKey::PaymentPhonepeEnabled,
            ],
            self::Dining => [
                WebsiteSettingKey::FulfilmentDineInEnabled,
                WebsiteSettingKey::FulfilmentDeliveryDisclaimer,
            ],
            self::Tax => [
                WebsiteSettingKey::TaxEnabled,
                WebsiteSettingKey::TaxLabel,
                WebsiteSettingKey::TaxPercent,
                WebsiteSettingKey::TaxInclusive,
                WebsiteSettingKey::TaxGstin,
                WebsiteSettingKey::TaxLegalBusinessName,
            ],
            self::Award => [
                WebsiteSettingKey::ReferralEnabled,
                WebsiteSettingKey::ReferralRewardType,
                WebsiteSettingKey::ReferralRewardProductId,
                WebsiteSettingKey::ReferralRewardVariantId,
                WebsiteSettingKey::ReferralRewardQuantity,
                WebsiteSettingKey::ReferralCouponDiscountType,
                WebsiteSettingKey::ReferralCouponDiscountValue,
                WebsiteSettingKey::ReferralCouponMaxDiscount,
                WebsiteSettingKey::ReferralCouponMinimumSubtotal,
                WebsiteSettingKey::ReferralMinimumQualifyingOrderAmount,
                WebsiteSettingKey::ReferralRewardRedemptionDurationDays,
                WebsiteSettingKey::ReferralMaxRewardsPerCustomerMonth,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Branding,
            self::Business,
            self::Ordering,
            self::Payments,
            self::Dining,
            self::Tax,
            self::Award,
        ];
    }

    public static function fromQuery(?string $value): self
    {
        $normalized = match (trim((string) $value)) {
            'advanced' => self::Award->value,
            default => trim((string) $value),
        };

        return self::tryFrom($normalized) ?? self::Branding;
    }
}
