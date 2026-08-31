<?php

namespace Tests\Unit;

use App\Enums\WebsiteSettingKey;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
use App\Services\Social\SocialLinkServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class WebsiteSettingPaymentPrecedenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_filled_settings_override_config_and_empty_settings_fall_back(): void
    {
        config()->set('coffee.payments.display_name', 'Config Name');
        config()->set('coffee.payments.instructions', 'Config instructions');
        config()->set('coffee.payments.upi_id', 'config@upi');
        config()->set('coffee.payments.phone', '+910000000002');
        config()->set('coffee.payments.qr_image_path', 'https://cdn.example.com/config-qr.png');
        config()->set('coffee.payments.whatsapp_number', '+910000000001');

        $repository = Mockery::mock(WebsiteSettingRepositoryInterface::class);
        $repository->shouldReceive('keyedValues')->once()->andReturn(Collection::make([
            WebsiteSettingKey::PaymentDisplayName->value => 'Settings Name',
            WebsiteSettingKey::PaymentInstructions->value => '   ',
            WebsiteSettingKey::PaymentUpiId->value => null,
            WebsiteSettingKey::PaymentPhone->value => null,
            WebsiteSettingKey::PaymentQrImagePath->value => 'https://cdn.example.com/settings-qr.png',
            WebsiteSettingKey::PaymentWhatsappNumber->value => '<b>+919999999999</b>',
        ]));

        $socialLinks = Mockery::mock(SocialLinkServiceInterface::class);
        $service = new WebsiteSettingService($repository, $socialLinks);
        $payment = $service->paymentInstructions();

        $this->assertSame('Settings Name', $payment['display_name']);
        $this->assertSame('Config instructions', $payment['instructions']);
        $this->assertSame('config@upi', $payment['upi_id']);
        $this->assertSame('+910000000002', $payment['phone']);
        $this->assertSame('https://cdn.example.com/settings-qr.png', $payment['qr_image_path']);
        $this->assertSame('+919999999999', $payment['whatsapp_number']);
    }

    public function test_delivery_disclaimer_uses_settings_then_config(): void
    {
        config()->set('coffee.fulfilment.delivery_disclaimer', 'Config disclaimer');

        $repository = Mockery::mock(WebsiteSettingRepositoryInterface::class);
        $repository->shouldReceive('keyedValues')->once()->andReturn(Collection::make([
            WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value => 'Settings disclaimer',
        ]));

        $socialLinks = Mockery::mock(SocialLinkServiceInterface::class);
        $service = new WebsiteSettingService($repository, $socialLinks);

        $this->assertSame('Settings disclaimer', $service->deliveryDisclaimer());

        $repositoryEmpty = Mockery::mock(WebsiteSettingRepositoryInterface::class);
        $repositoryEmpty->shouldReceive('keyedValues')->once()->andReturn(Collection::make([
            WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value => null,
        ]));

        $serviceEmpty = new WebsiteSettingService($repositoryEmpty, $socialLinks);

        $this->assertSame('Config disclaimer', $serviceEmpty->deliveryDisclaimer());
    }
}
