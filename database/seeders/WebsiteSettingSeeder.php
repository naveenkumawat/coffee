<?php

namespace Database\Seeders;

use App\Enums\WebsiteSettingKey;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class WebsiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $values = [
            WebsiteSettingKey::HeroTitle->value => 'Brew your next order before you arrive.',
            WebsiteSettingKey::HeroSubtitle->value => 'Browse the live menu, customise your cup, and pick up when it is ready.',
            WebsiteSettingKey::HeroImagePath->value => null,
            WebsiteSettingKey::BusinessName->value => 'Coffee Cafe',
            WebsiteSettingKey::BusinessAboutShort->value => 'Neighborhood espresso bar focused on fast pickup and carefully crafted drinks.',
            WebsiteSettingKey::BusinessPhone->value => '+91 98765 43210',
            WebsiteSettingKey::BusinessWhatsappNumber->value => '+919876543210',
            WebsiteSettingKey::BusinessEmail->value => 'hello@coffee.local',
            WebsiteSettingKey::BusinessAddress->value => "12 Brew Street\nIndiranagar, Bengaluru 560038",
            WebsiteSettingKey::BusinessOpeningHours->value => "Mon–Fri: 8:00 AM – 9:00 PM\nSat–Sun: 9:00 AM – 10:00 PM",
            WebsiteSettingKey::PaymentDisplayName->value => 'UPI Transfer',
            WebsiteSettingKey::PaymentInstructions->value => 'Pay the order total via UPI, then share your payment screenshot with the order number on WhatsApp.',
            WebsiteSettingKey::PaymentUpiId->value => 'coffee@upi',
            WebsiteSettingKey::PaymentWhatsappNumber->value => '+919876543210',
            WebsiteSettingKey::PagesAbout->value => "Coffee Cafe is a pickup-first neighborhood cafe.\n\nWe keep the menu focused, the espresso consistent, and the wait short so you can order ahead and collect when ready.",
            WebsiteSettingKey::PagesContact->value => "Need help with an order or pickup timing?\n\nCall, email, or WhatsApp us and include your order number when you can.",
            WebsiteSettingKey::PagesFaq->value => "How do I order?\nBrowse the menu, add items to your cart, and checkout while signed in.\n\nWhen do I pay?\nOrders start as Pending Payment. Pay by UPI and share the screenshot on WhatsApp.\n\nCan I customise drinks?\nYes where a product is marked customizable. Flavours and sizes appear on the product page.",
            WebsiteSettingKey::PagesTerms->value => "Orders placed in the Coffee Cafe app are for cafe pickup only.\n\nPrices and availability are confirmed by the cafe at checkout time.\n\nPayment remains pending until the cafe team confirms your transfer.",
            WebsiteSettingKey::PagesPrivacy->value => "We use your name, email, and phone to process pickup orders and share payment instructions.\n\nAccount data is not sold to third parties.\n\nContact hello@coffee.local for privacy questions.",
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
