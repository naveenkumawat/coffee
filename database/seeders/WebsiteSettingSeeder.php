<?php

namespace Database\Seeders;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

/**
 * Local/testing demo CMS values only.
 *
 * Production must configure Website Settings in Administrator after migrate.
 * Never treat these phones/UPI/address values as live café configuration.
 */
class WebsiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $values = [
            WebsiteSettingKey::HeroTitle->value => 'Brew your next order before you arrive.',
            WebsiteSettingKey::HeroSubtitle->value => 'Sip. Relax. Enjoy.',
            WebsiteSettingKey::HeroImagePath->value => null,
            WebsiteSettingKey::BusinessName->value => 'The88Coffees',
            WebsiteSettingKey::BusinessAboutShort->value => 'Neighborhood espresso bar focused on carefully crafted drinks, takeaway, and third-party delivery.',
            WebsiteSettingKey::BusinessPhone->value => '+91 98765 43210',
            WebsiteSettingKey::BusinessWhatsappNumber->value => '+919876543210',
            WebsiteSettingKey::BusinessEmail->value => 'hello@coffee.local',
            WebsiteSettingKey::BusinessAddress->value => "12 Brew Street\nIndiranagar, Bengaluru 560038",
            WebsiteSettingKey::BusinessOpeningHours->value => "Mon–Fri: 8:00 AM – 9:00 PM\nSat–Sun: 9:00 AM – 10:00 PM",
            WebsiteSettingKey::PaymentDisplayName->value => 'UPI Transfer',
            WebsiteSettingKey::PaymentInstructions->value => 'Pay the order total via UPI or to the payment number, upload your screenshot in the app, or share it on WhatsApp with the order number.',
            WebsiteSettingKey::PaymentUpiId->value => 'coffee@upi',
            WebsiteSettingKey::PaymentPhone->value => '+91 98765 43210',
            WebsiteSettingKey::PaymentQrImagePath->value => null,
            WebsiteSettingKey::PaymentWhatsappNumber->value => '+919876543210',
            WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value => 'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.',
            WebsiteSettingKey::FulfilmentDineInEnabled->value => '0',
            WebsiteSettingKey::PagesAbout->value => "The88Coffees is a neighborhood cafe built for order-ahead takeaway and third-party delivery.\n\nWe keep the menu focused, the espresso consistent, and the wait short so you can order ahead and collect when ready.",
            WebsiteSettingKey::PagesContact->value => "Need help with an order or timing?\n\nCall, email, or WhatsApp us and include your order number when you can.",
            WebsiteSettingKey::PagesFaq->value => "How do I order?\nBrowse the menu, add items to your cart, and checkout while signed in.\n\nWhen do I pay?\nOrders start as Pending Payment. Pay by UPI and upload the screenshot in the app (or WhatsApp).\n\nDo you deliver?\nYes — choose Delivery at checkout. A third-party service arranges delivery and charges you separately.\n\nCan I customise drinks?\nYes where a product is marked customizable. Flavours and sizes appear on the product page.",
            WebsiteSettingKey::PagesTerms->value => "Orders placed through The88Coffees support takeaway and third-party delivery.\n\nPrices and availability are confirmed by the cafe at checkout time. Delivery charges are paid separately to the delivery provider.\n\nPayment remains pending until the cafe team confirms your transfer.",
            WebsiteSettingKey::PagesPrivacy->value => "We use your name, email, and phone to process takeaway or delivery orders and share payment instructions.\n\nAccount data is not sold to third parties.\n\nContact hello@coffee.local for privacy questions.",
        ];

        foreach (WebsiteSettingKey::ordered() as $key) {
            WebsiteSetting::query()->updateOrCreate(
                ['key' => $key->value],
                [
                    'section' => $key->section(),
                    'value_type' => $key->valueType(),
                    'value' => $values[$key->value] ?? null,
                ],
            );
        }
    }
}
