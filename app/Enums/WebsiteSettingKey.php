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
            self::PagesAbout,
            self::PagesContact,
            self::PagesFaq,
            self::PagesTerms,
            self::PagesPrivacy => 'text',
            default => 'string',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::HeroTitle => 'Hero title',
            self::HeroSubtitle => 'Hero subtitle',
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
            self::PagesAbout => 'About page',
            self::PagesContact => 'Contact page',
            self::PagesFaq => 'FAQ page',
            self::PagesTerms => 'Terms page',
            self::PagesPrivacy => 'Privacy page',
        };
    }

    public function maxLength(): int
    {
        return match ($this) {
            self::HeroTitle, self::BusinessName, self::PaymentDisplayName => 120,
            self::HeroImagePath, self::BusinessPhone, self::BusinessWhatsappNumber, self::BusinessEmail, self::PaymentUpiId, self::PaymentPhone, self::PaymentQrImagePath, self::PaymentWhatsappNumber => 255,
            self::HeroSubtitle, self::BusinessAboutShort => 1000,
            self::BusinessAddress, self::BusinessOpeningHours, self::PaymentInstructions => 2000,
            self::PagesAbout, self::PagesContact, self::PagesFaq, self::PagesTerms, self::PagesPrivacy => 20000,
        };
    }

    public function formInputType(): string
    {
        return match ($this) {
            self::BusinessEmail => 'email',
            self::BusinessPhone, self::BusinessWhatsappNumber, self::PaymentPhone, self::PaymentWhatsappNumber => 'tel',
            default => 'text',
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
