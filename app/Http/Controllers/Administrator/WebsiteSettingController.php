<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\WebsiteSettingKey;
use App\Enums\WebsiteSettingSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteSetting\WebsiteSettingUpdateRequest;
use App\Models\Product;
use App\Models\WebsiteSetting;
use App\Services\Payment\PaymentMethodCatalog;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use App\Support\PublicMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteSettingController extends Controller
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected PaymentMethodCatalog $paymentMethods,
    ) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', WebsiteSetting::class);

        $legacy = $this->legacySectionRedirect($request->query('section'));

        if ($legacy instanceof RedirectResponse) {
            return $legacy;
        }

        $section = WebsiteSettingSection::fromQuery($request->query('section'));

        return view('administrator.website-settings.edit', [
            'section' => $section,
            'sections' => WebsiteSettingSection::ordered(),
            'values' => $this->websiteSettings->valuesForAdmin(),
            'keys' => $section->settingKeys(),
            'referralProducts' => $section === WebsiteSettingSection::Award
                ? Product::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'paymentMethodDiagnostics' => $section === WebsiteSettingSection::Payments
                ? $this->paymentMethods->adminDiagnostics()
                : [],
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

        $incoming = $request->incomingSection();

        if (in_array($incoming, ['hero', 'social'], true)) {
            $legacy = $this->legacySectionRedirect($incoming);

            if ($legacy instanceof RedirectResponse) {
                return $legacy;
            }
        }

        $section = $request->section();
        $redirect = redirect()->route('administrator.website-settings.edit', [
            'section' => $section->value,
        ]);

        $payload = $request->safe()->except([
            'section',
            'payment_qr_image',
            'brand_logo',
            'remove_payment_qr_image',
            'remove_brand_logo',
        ]);

        $current = $this->websiteSettings->valuesForAdmin();

        if ($section === WebsiteSettingSection::Branding) {
            $this->syncWebsiteImage($request, $payload, $current, 'brand_logo', 'remove_brand_logo', WebsiteSettingKey::BrandLogoPath);
        }

        if ($section === WebsiteSettingSection::Payments) {
            $this->syncWebsiteImage($request, $payload, $current, 'payment_qr_image', 'remove_payment_qr_image', WebsiteSettingKey::PaymentQrImagePath);
        }

        $allowed = array_map(fn (WebsiteSettingKey $key): string => $key->value, $section->settingKeys());
        $payload = array_intersect_key($payload, array_flip($allowed));

        foreach ($section->settingKeys() as $key) {
            if ($key->valueType() !== 'boolean') {
                continue;
            }

            $payload[$key->value] = $request->boolean($key->value) ? '1' : '0';
        }

        $this->websiteSettings->update($payload);

        return $redirect->with('status', $section->label().' settings updated successfully.');
    }

    protected function legacySectionRedirect(mixed $section): ?RedirectResponse
    {
        return match (trim((string) $section)) {
            'hero' => redirect()->route('administrator.hero.edit'),
            'social' => redirect()->route('administrator.social-links.index'),
            'advanced' => redirect()->route('administrator.website-settings.edit', [
                'section' => WebsiteSettingSection::Award->value,
            ]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $current
     */
    protected function syncWebsiteImage(
        WebsiteSettingUpdateRequest $request,
        array &$payload,
        array $current,
        string $fileInput,
        string $removeInput,
        WebsiteSettingKey $pathKey,
    ): void {
        if ($request->hasFile($fileInput)) {
            $previous = $current[$pathKey->value] ?? null;
            $payload[$pathKey->value] = $fileInput === 'brand_logo'
                ? PublicMedia::storeBrandLogo($request->file($fileInput))
                : PublicMedia::store(
                    $request->file($fileInput),
                    PublicMedia::DIRECTORY_WEBSITE,
                );
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);

            return;
        }

        if ($request->boolean($removeInput)) {
            $previous = $current[$pathKey->value] ?? null;
            $payload[$pathKey->value] = null;
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);

            return;
        }

        unset($payload[$pathKey->value]);
    }
}
