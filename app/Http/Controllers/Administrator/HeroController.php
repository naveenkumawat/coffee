<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\WebsiteSettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hero\HeroUpdateRequest;
use App\Models\WebsiteSetting;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use App\Support\PublicMedia;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HeroController extends Controller
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function edit(): View
    {
        $this->authorize('viewAny', WebsiteSetting::class);

        $values = $this->websiteSettings->valuesForAdmin();

        return view('administrator.hero.edit', [
            'values' => $values,
            'title' => $values[WebsiteSettingKey::HeroTitle->value] ?? null,
            'imagePath' => $values[WebsiteSettingKey::HeroImagePath->value] ?? null,
        ]);
    }

    public function update(HeroUpdateRequest $request): RedirectResponse
    {
        $this->authorize('update', WebsiteSetting::class);

        $payload = $request->safe()->except([
            'hero_image',
            'remove_hero_image',
        ]);

        $current = $this->websiteSettings->valuesForAdmin();
        $this->syncHeroImage($request, $payload, $current);

        $allowed = [
            WebsiteSettingKey::HeroTitle->value,
            WebsiteSettingKey::HeroImagePath->value,
        ];
        $payload = array_intersect_key($payload, array_flip($allowed));

        $this->websiteSettings->update($payload);

        return redirect()
            ->route('administrator.hero.edit')
            ->with('status', 'Hero saved successfully.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $current
     */
    protected function syncHeroImage(HeroUpdateRequest $request, array &$payload, array $current): void
    {
        $pathKey = WebsiteSettingKey::HeroImagePath;

        if ($request->hasFile('hero_image')) {
            $previous = $current[$pathKey->value] ?? null;
            $payload[$pathKey->value] = PublicMedia::store(
                $request->file('hero_image'),
                PublicMedia::DIRECTORY_WEBSITE,
            );
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);

            return;
        }

        if ($request->boolean('remove_hero_image')) {
            $previous = $current[$pathKey->value] ?? null;
            $payload[$pathKey->value] = null;
            PublicMedia::deleteManaged(is_string($previous) ? $previous : null);

            return;
        }

        if (! array_key_exists($pathKey->value, $payload)) {
            unset($payload[$pathKey->value]);
        }
    }
}
