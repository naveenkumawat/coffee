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
    case BusinessTimezone = 'business_timezone';
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
    case OrderSecurityEnabled = 'order_security_enabled';
    case OrderSecurityMaxOpenUnpaidOrders = 'order_security_max_open_unpaid_orders';
    case OrderSecurityMaxOrdersPerHour = 'order_security_max_orders_per_hour';
    case OrderSecurityCheckoutAttemptsPer10Minutes = 'order_security_checkout_attempts_per_10_minutes';
    case OrderSecurityPaymentProofAttemptsPer15Minutes = 'order_security_payment_proof_attempts_per_15_minutes';
    case OrderSecurityDuplicateOrderWindowMinutes = 'order_security_duplicate_order_window_minutes';
    case OrderingManualClosed = 'ordering_manual_closed';
    case OrderingManualClosedUntil = 'ordering_manual_closed_until';
    case OrderingManualClosedMessage = 'ordering_manual_closed_message';
    case PagesAbout = 'pages_about';
    case PagesContact = 'pages_contact';
    case PagesFaq = 'pages_faq';
    case PagesTerms = 'pages_terms';
    case PagesPrivacy = 'pages_privacy';

    public function section(): string
    {
        return match ($this) {
            self::HeroTitle, self::HeroSubtitle, self::HeroImagePath => 'hero',
            self::BusinessName, self::BusinessAboutShort, self::BusinessPhone, self::BusinessWhatsappNumber, self::BusinessEmail, self::BusinessAddress, self::BusinessOpeningHours, self::BusinessTimezone => 'business',
            self::PaymentDisplayName, self::PaymentInstructions, self::PaymentUpiId, self::PaymentPhone, self::PaymentQrImagePath, self::PaymentWhatsappNumber => 'payment',
            self::FulfilmentDeliveryDisclaimer, self::FulfilmentDineInEnabled => 'fulfilment',
            self::TaxEnabled, self::TaxLabel, self::TaxPercent, self::TaxInclusive, self::TaxGstin, self::TaxLegalBusinessName => 'tax',
            self::OrderSecurityEnabled,
            self::OrderSecurityMaxOpenUnpaidOrders,
            self::OrderSecurityMaxOrdersPerHour,
            self::OrderSecurityCheckoutAttemptsPer10Minutes,
            self::OrderSecurityPaymentProofAttemptsPer15Minutes,
            self::OrderSecurityDuplicateOrderWindowMinutes => 'order_security',
            self::OrderingManualClosed,
            self::OrderingManualClosedUntil,
            self::OrderingManualClosedMessage => 'cafe_ordering',
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
            self::FulfilmentDineInEnabled, self::TaxEnabled, self::TaxInclusive, self::OrderSecurityEnabled, self::OrderingManualClosed => 'boolean',
            self::OrderSecurityMaxOpenUnpaidOrders,
            self::OrderSecurityMaxOrdersPerHour,
            self::OrderSecurityCheckoutAttemptsPer10Minutes,
            self::OrderSecurityPaymentProofAttemptsPer15Minutes,
            self::OrderSecurityDuplicateOrderWindowMinutes => 'integer',
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
            self::BusinessOpeningHours => 'Opening hours (display text)',
            self::BusinessTimezone => 'Business timezone',
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
            self::OrderSecurityEnabled => 'Enable order abuse protection',
            self::OrderSecurityMaxOpenUnpaidOrders => 'Max open unpaid orders',
            self::OrderSecurityMaxOrdersPerHour => 'Max new orders per hour',
            self::OrderSecurityCheckoutAttemptsPer10Minutes => 'Checkout attempts / 10 minutes',
            self::OrderSecurityPaymentProofAttemptsPer15Minutes => 'Payment proof attempts / 15 minutes',
            self::OrderSecurityDuplicateOrderWindowMinutes => 'Duplicate order window (minutes)',
            self::OrderingManualClosed => 'Ordering manually closed',
            self::OrderingManualClosedUntil => 'Manual closed until',
            self::OrderingManualClosedMessage => 'Manual closed customer message',
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
            self::HeroImagePath, self::BusinessPhone, self::BusinessWhatsappNumber, self::BusinessEmail, self::PaymentUpiId, self::PaymentPhone, self::PaymentQrImagePath, self::PaymentWhatsappNumber, self::TaxGstin, self::BusinessTimezone, self::OrderingManualClosedUntil => 255,
            self::TaxLabel => 40,
            self::TaxPercent => 8,
            self::OrderingManualClosedMessage => 500,
            self::OrderSecurityMaxOpenUnpaidOrders,
            self::OrderSecurityMaxOrdersPerHour,
            self::OrderSecurityCheckoutAttemptsPer10Minutes,
            self::OrderSecurityPaymentProofAttemptsPer15Minutes,
            self::OrderSecurityDuplicateOrderWindowMinutes => 3,
            self::HeroSubtitle, self::BusinessAboutShort => 1000,
            self::BusinessAddress, self::BusinessOpeningHours, self::PaymentInstructions, self::FulfilmentDeliveryDisclaimer => 2000,
            self::FulfilmentDineInEnabled, self::TaxEnabled, self::TaxInclusive, self::OrderSecurityEnabled, self::OrderingManualClosed => 1,
            self::PagesAbout, self::PagesContact, self::PagesFaq, self::PagesTerms, self::PagesPrivacy => 20000,
        };
    }

    public function formInputType(): string
    {
        return match ($this) {
            self::BusinessEmail => 'email',
            self::BusinessPhone, self::BusinessWhatsappNumber, self::PaymentPhone, self::PaymentWhatsappNumber => 'tel',
            self::FulfilmentDineInEnabled, self::TaxEnabled, self::TaxInclusive, self::OrderSecurityEnabled, self::OrderingManualClosed => 'checkbox',
            self::TaxPercent,
            self::OrderSecurityMaxOpenUnpaidOrders,
            self::OrderSecurityMaxOrdersPerHour,
            self::OrderSecurityCheckoutAttemptsPer10Minutes,
            self::OrderSecurityPaymentProofAttemptsPer15Minutes,
            self::OrderSecurityDuplicateOrderWindowMinutes => 'number',
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
            self::BusinessTimezone => 'IANA timezone used for operating hours and closures (e.g. Asia/Kolkata). Never use the customer browser timezone.',
            self::OrderSecurityEnabled => 'When enabled, pending-order limits, duplicate detection, and order rate limits apply at checkout.',
            self::OrderSecurityMaxOpenUnpaidOrders => 'Customers cannot place another order while they already have this many unpaid/open orders (1–20).',
            self::OrderSecurityMaxOrdersPerHour => 'Successful new orders per customer per rolling hour (1–60).',
            self::OrderSecurityCheckoutAttemptsPer10Minutes => 'Checkout submission attempts per customer per 10 minutes (1–60).',
            self::OrderSecurityPaymentProofAttemptsPer15Minutes => 'Payment proof uploads per customer/order per 15 minutes (1–60).',
            self::OrderSecurityDuplicateOrderWindowMinutes => 'Reuse identical cart/fulfilment/payment intent within this window instead of creating duplicates (1–30).',
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
