<?php

namespace Tests\Unit;

use App\Enums\WebsiteSettingKey;
use App\Repositories\WebsiteSetting\WebsiteSettingRepositoryInterface;
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
        config()->set('coffee.payments.qr_image_path', 'config/qr.png');
        config()->set('coffee.payments.whatsapp_number', '+910000000001');

        $repository = Mockery::mock(WebsiteSettingRepositoryInterface::class);
        $repository->shouldReceive('keyedValues')->once()->andReturn(Collection::make([
            WebsiteSettingKey::PaymentDisplayName->value => 'Settings Name',
            WebsiteSettingKey::PaymentInstructions->value => '   ',
            WebsiteSettingKey::PaymentUpiId->value => null,
            WebsiteSettingKey::PaymentPhone->value => null,
            WebsiteSettingKey::PaymentQrImagePath->value => 'settings/qr.png',
            WebsiteSettingKey::PaymentWhatsappNumber->value => '<b>+919999999999</b>',
        ]));

        $service = new WebsiteSettingService($repository);
        $payment = $service->paymentInstructions();

        $this->assertSame('Settings Name', $payment['display_name']);
        $this->assertSame('Config instructions', $payment['instructions']);
        $this->assertSame('config@upi', $payment['upi_id']);
        $this->assertSame('+910000000002', $payment['phone']);
        $this->assertSame('settings/qr.png', $payment['qr_image_path']);
        $this->assertSame('+919999999999', $payment['whatsapp_number']);
    }
}
