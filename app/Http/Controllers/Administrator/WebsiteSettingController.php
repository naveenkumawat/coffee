<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\WebsiteSettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteSetting\WebsiteSettingUpdateRequest;
use App\Models\WebsiteSetting;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use App\Support\PublicMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class WebsiteSettingController extends Controller
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function edit(): View
    {
        $this->authorize('viewAny', WebsiteSetting::class);

        return view('administrator.website-settings.edit', [
            'values' => $this->websiteSettings->valuesForAdmin(),
            'keys' => WebsiteSettingKey::ordered(),
            'paymentConfig' => [
                'display_name' => config('coffee.payments.display_name'),
                'instructions' => config('coffee.payments.instructions'),
                'upi_id' => config('coffee.payments.upi_id'),
                'phone' => config('coffee.payments.phone'),
                'qr_image_path' => config('coffee.payments.qr_image_path'),
                'whatsapp_number' => config('coffee.payments.whatsapp_number'),
            ],
            'fulfilmentConfig' => [
                'delivery_disclaimer' => config('coffee.fulfilment.delivery_disclaimer'),
            ],
        ]);
    }

    public function update(WebsiteSettingUpdateRequest $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $payload = $request->safe()->except([
            'hero_image',
            'payment_qr_image',
            'remove_hero_image',
            'remove_payment_qr_image',
        ]);

        $current = $this->websiteSettings->valuesForAdmin();

        if ($request->hasFile('hero_image')) {
            $previous = $current[WebsiteSettingKey::HeroImagePath->value] ?? null;
            $payload[WebsiteSettingKey::HeroImagePath->value] = PublicMedia::store(
                $request->file('hero_image'),
                PublicMedia::DIRECTORY_WEBSITE,
            );
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);
        } elseif ($request->boolean('remove_hero_image')) {
            $previous = $current[WebsiteSettingKey::HeroImagePath->value] ?? null;
            $payload[WebsiteSettingKey::HeroImagePath->value] = null;
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);
        }

        if ($request->hasFile('payment_qr_image')) {
            $previous = $current[WebsiteSettingKey::PaymentQrImagePath->value] ?? null;
            $payload[WebsiteSettingKey::PaymentQrImagePath->value] = PublicMedia::store(
                $request->file('payment_qr_image'),
                PublicMedia::DIRECTORY_WEBSITE,
            );
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);
        } elseif ($request->boolean('remove_payment_qr_image')) {
            $previous = $current[WebsiteSettingKey::PaymentQrImagePath->value] ?? null;
            $payload[WebsiteSettingKey::PaymentQrImagePath->value] = null;
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);
        }

        $payload[WebsiteSettingKey::FulfilmentDineInEnabled->value] = $request->boolean(
            WebsiteSettingKey::FulfilmentDineInEnabled->value,
        ) ? '1' : '0';
        $payload[WebsiteSettingKey::TaxEnabled->value] = $request->boolean(
            WebsiteSettingKey::TaxEnabled->value,
        ) ? '1' : '0';
        $payload[WebsiteSettingKey::TaxInclusive->value] = $request->boolean(
            WebsiteSettingKey::TaxInclusive->value,
        ) ? '1' : '0';
        $payload[WebsiteSettingKey::OrderSecurityEnabled->value] = $request->boolean(
            WebsiteSettingKey::OrderSecurityEnabled->value,
        ) ? '1' : '0';
        $payload[WebsiteSettingKey::ReferralEnabled->value] = $request->boolean(
            WebsiteSettingKey::ReferralEnabled->value,
        ) ? '1' : '0';

        $this->websiteSettings->update($payload);

        return redirect()
            ->route('administrator.website-settings.edit')
            ->with('status', 'Website settings updated successfully.');
    }
}
