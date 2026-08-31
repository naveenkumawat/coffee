<?php

namespace App\Enums;

enum WebsiteSettingKey: string
{
    case HeroTitle = 'hero_title';
    case HeroSubtitle = 'hero_subtitle';
    case HeroImagePath = 'hero_image_path';
    case BusinessName = 'business_name';
    case BusinessAboutShort = 'business_about_short';
    case BusinessPhone = 'business_phone';
    case BusinessWhatsappNumber = 'business_whatsapp_number';
    case BusinessEmail = 'business_email';
    case BusinessAddress = 'business_address';
    case BusinessOpeningHours = 'business_opening_hours';
    case PaymentDisplayName = 'payment_display_name';
    case PaymentInstructions = 'payment_instructions';
    case PaymentUpiId = 'payment_upi_id';
    case PaymentPhone = 'payment_phone';
    case PaymentQrImagePath = 'payment_qr_image_path';
    case PaymentWhatsappNumber = 'payment_whatsapp_number';
    case FulfilmentDeliveryDisclaimer = 'fulfilment_delivery_disclaimer';
    case FulfilmentDineInEnabled = 'fulfilment_dine_in_enabled';
    case TaxEnabled = 'tax_enabled';
    case TaxLabel = 'tax_label';
    case TaxPercent = 'tax_percent';
    case TaxInclusive = 'tax_inclusive';
    case TaxGstin = 'tax_gstin';
    case TaxLegalBusinessName = 'tax_legal_business_name';
    case PagesAbout = 'pages_about';
    case PagesContact = 'pages_contact';
    case PagesFaq = 'pages_faq';
    case PagesTerms = 'pages_terms';
    case PagesPrivacy = 'pages_privacy';

    public function section(): string
    {
        return match ($this) {
            self::HeroTitle, self::HeroSubtitle, self::HeroImagePath => 'hero',
            self::BusinessName, self::BusinessAboutShort, self::BusinessPhone, self::BusinessWhatsappNumber, self::BusinessEmail, self::BusinessAddress, self::BusinessOpeningHours => 'business',
            self::PaymentDisplayName, self::PaymentInstructions, self::PaymentUpiId, self::PaymentPhone, self::PaymentQrImagePath, self::PaymentWhatsappNumber => 'payment',
            self::FulfilmentDeliveryDisclaimer, self::FulfilmentDineInEnabled => 'fulfilment',
            self::TaxEnabled, self::TaxLabel, self::TaxPercent, self::TaxInclusive, self::TaxGstin, self::TaxLegalBusinessName => 'tax',
            self::PagesAbout, self::PagesContact, self::PagesFaq, self::PagesTerms, self::PagesPrivacy => 'pages',
        };
    }

    public function valueType(): string
    {
        return match ($this) {
            self::HeroSubtitle,
            self::BusinessAboutShort,
            self::BusinessAddress,
            self::BusinessOpeningHours,
            self::PaymentInstructions,
            self::FulfilmentDeliveryDisclaimer => 'text',
            self::FulfilmentDineInEnabled, self::TaxEnabled, self::TaxInclusive => 'boolean',
            default => 'string',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::HeroTitle => 'Hero title',
            self::HeroSubtitle => 'Home slogan / hero subtitle',
            self::HeroImagePath => 'Hero image',
            self::BusinessName => 'Business name',
            self::BusinessAboutShort => 'Short about text',
            self::BusinessPhone => 'Phone',
            self::BusinessWhatsappNumber => 'WhatsApp number',
            self::BusinessEmail => 'Email',
            self::BusinessAddress => 'Address',
            self::BusinessOpeningHours => 'Opening hours',
            self::PaymentDisplayName => 'Payment display name',
            self::PaymentInstructions => 'Payment instructions',
            self::PaymentUpiId => 'UPI ID',
            self::PaymentPhone => 'Payment phone / number',
            self::PaymentQrImagePath => 'Payment QR image',
            self::PaymentWhatsappNumber => 'Payment WhatsApp number',
            self::FulfilmentDeliveryDisclaimer => 'Delivery disclaimer',
            self::FulfilmentDineInEnabled => 'Enable dine-in / table ordering',
            self::TaxEnabled => 'Enable GST',
            self::TaxLabel => 'Tax label',
            self::TaxPercent => 'GST %',
            self::TaxInclusive => 'Prices already include GST',
            self::TaxGstin => 'GSTIN (optional)',
            self::TaxLegalBusinessName => 'Legal business name (optional)',
            self::PagesAbout => 'About page',
            self::PagesContact => 'Contact / Visit page',
            self::PagesFaq => 'FAQ page',
            self::PagesTerms => 'Terms page',
            self::PagesPrivacy => 'Privacy page',
        };
    }

    public function maxLength(): int
    {
        return match ($this) {
            self::HeroTitle, self::BusinessName, self::PaymentDisplayName, self::TaxLegalBusinessName => 120,
            self::HeroImagePath, self::BusinessPhone, self::BusinessWhatsappNumber, self::BusinessEmail, self::PaymentUpiId, self::PaymentPhone, self::PaymentQrImagePath, self::PaymentWhatsappNumber, self::TaxGstin => 255,
            self::TaxLabel => 40,
            self::TaxPercent => 8,
            self::HeroSubtitle, self::BusinessAboutShort => 1000,
            self::BusinessAddress, self::BusinessOpeningHours, self::PaymentInstructions, self::FulfilmentDeliveryDisclaimer => 2000,
            self::FulfilmentDineInEnabled, self::TaxEnabled, self::TaxInclusive => 1,
            self::PagesAbout, self::PagesContact, self::PagesFaq, self::PagesTerms, self::PagesPrivacy => 20000,
        };
    }

    public function formInputType(): string
    {
        return match ($this) {
            self::BusinessEmail => 'email',
            self::BusinessPhone, self::BusinessWhatsappNumber, self::PaymentPhone, self::PaymentWhatsappNumber => 'tel',
            self::FulfilmentDineInEnabled, self::TaxEnabled, self::TaxInclusive => 'checkbox',
            self::TaxPercent => 'number',
            default => 'text',
        };
    }

    public function helpText(): ?string
    {
        return match ($this) {
            self::TaxEnabled => 'When enabled, GST is calculated on the café subtotal (not third-party delivery fees) and stored on each order.',
            self::TaxLabel => 'Shown on checkout, orders, and invoices (default GST).',
            self::TaxPercent => 'Percentage from 0 to 100, e.g. 5.00.',
            self::TaxInclusive => 'Off = exclusive (GST added to subtotal). On = inclusive (menu prices already include GST).',
            self::TaxGstin => 'Printed on invoices only when set. Leave blank if not applicable.',
            self::TaxLegalBusinessName => 'Optional legal name for invoices when different from the café display brand.',
            self::FulfilmentDineInEnabled => 'Allow customers to place dine-in / table orders from the PWA. Manage café tables under Café Tables.',
            default => null,
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }
}
