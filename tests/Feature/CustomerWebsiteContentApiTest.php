<?php

namespace Tests\Feature;

use App\Enums\WebsiteSettingKey;
use App\Models\Order;
use App\Models\User;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWebsiteContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_customer_safe_website_content(): void
    {
        config()->set('coffee.company.name', 'Config Cafe');
        config()->set('coffee.company.support_email', 'hello@config.test');
        config()->set('coffee.payments.display_name', 'Config Pay');
        config()->set('coffee.payments.instructions', 'Pay with config UPI.');
        config()->set('coffee.payments.upi_id', 'config@upi');
        config()->set('coffee.payments.whatsapp_number', '+910000000001');

        WebsiteSetting::query()->where('key', WebsiteSettingKey::HeroTitle->value)->update([
            'value' => 'Fresh brew awaits',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::BusinessAboutShort->value)->update([
            'value' => 'Neighborhood coffee, pickup ready.',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::BusinessWhatsappNumber->value)->update([
            'value' => '+919898989898',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::PagesAbout->value)->update([
            'value' => '<script>alert(1)</script>About our cafe',
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.hero.title', 'Fresh brew awaits')
            ->assertJsonPath('data.business.name', 'Config Cafe')
            ->assertJsonPath('data.business.about_short', 'Neighborhood coffee, pickup ready.')
            ->assertJsonPath('data.business.whatsapp_number', '+919898989898')
            ->assertJsonPath('data.business.email', 'hello@config.test')
            ->assertJsonPath('data.payment.display_name', 'Config Pay')
            ->assertJsonPath('data.payment.upi_id', 'config@upi')
            ->assertJsonPath('data.pages.about', 'About our cafe')
            ->assertJsonPath(
                'data.fulfilment.delivery_disclaimer',
                'Delivery will be arranged through a third-party service. Delivery charges are payable separately by the customer.',
            )
            ->assertJsonMissingPath('data.internal')
            ->assertJsonMissing(['data' => ['pages' => ['about' => '<script>alert(1)</script>About our cafe']]]);
    }

    public function test_delivery_disclaimer_setting_overrides_config(): void
    {
        config()->set(
            'coffee.fulfilment.delivery_disclaimer',
            'Config delivery disclaimer only.',
        );

        WebsiteSetting::query()->where('key', WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value)->update([
            'value' => 'Settings delivery disclaimer for customers.',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::HeroSubtitle->value)->update([
            'value' => 'Sip. Relax. Enjoy.',
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.hero.subtitle', 'Sip. Relax. Enjoy.')
            ->assertJsonPath('data.fulfilment.delivery_disclaimer', 'Settings delivery disclaimer for customers.');
    }

    public function test_payment_settings_override_config_when_filled(): void
    {
        config()->set('coffee.payments.display_name', 'Config Pay');
        config()->set('coffee.payments.instructions', 'Config instructions');
        config()->set('coffee.payments.upi_id', 'config@upi');
        config()->set('coffee.payments.whatsapp_number', '+910000000001');

        WebsiteSetting::query()->where('key', WebsiteSettingKey::PaymentDisplayName->value)->update([
            'value' => 'Cafe UPI',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::PaymentUpiId->value)->update([
            'value' => 'cafe@upi',
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.payment.display_name', 'Cafe UPI')
            ->assertJsonPath('data.payment.upi_id', 'cafe@upi')
            ->assertJsonPath('data.payment.instructions', 'Config instructions')
            ->assertJsonPath('data.payment.whatsapp_number', '+910000000001');
    }

    public function test_order_payment_meta_uses_settings_precedence(): void
    {
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
        ]);

        config()->set('coffee.payments.display_name', 'Config Pay');
        config()->set('coffee.payments.instructions', 'Config instructions');
        config()->set('coffee.payments.upi_id', 'config@upi');
        config()->set('coffee.payments.whatsapp_number', '+910000000001');

        WebsiteSetting::query()->where('key', WebsiteSettingKey::PaymentDisplayName->value)->update([
            'value' => 'Settings Pay',
        ]);

        $this->actingAs($customer, 'web')
            ->getJson(route('api.v1.orders.show', $order))
            ->assertOk()
            ->assertJsonPath('meta.payment.display_name', 'Settings Pay')
            ->assertJsonPath('meta.payment.upi_id', 'config@upi');
    }
}
