<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\WebsiteSettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteSetting\WebsiteSettingUpdateRequest;
use App\Models\WebsiteSetting;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
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
                'whatsapp_number' => config('coffee.payments.whatsapp_number'),
            ],
        ]);
    }

    public function update(WebsiteSettingUpdateRequest $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $this->websiteSettings->update($request->validated());

        return redirect()
            ->route('administrator.website-settings.edit')
            ->with('status', 'Website settings updated successfully.');
    }
}
