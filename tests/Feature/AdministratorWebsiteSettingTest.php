<?php

namespace Tests\Feature;

use App\Enums\BrandDisplayMode;
use App\Enums\WebsiteSettingKey;
use App\Enums\WebsiteSettingSection;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\PublicMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministratorWebsiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_categorized_website_settings(): void
    {
        $manager = User::factory()->manager()->create();

        $this->assertSame(
            ['Branding', 'Business', 'Ordering', 'Payments', 'Dining', 'Tax', 'Award'],
            array_map(fn (WebsiteSettingSection $section): string => $section->label(), WebsiteSettingSection::ordered()),
        );

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'branding']))
            ->assertOk()
            ->assertSee('Website Settings')
            ->assertSee('Branding')
            ->assertSee('Primary logo')
            ->assertSee('Brand display')
            ->assertSee('Logo only')
            ->assertSee('Award')
            ->assertDontSee('Favicon / App icon')
            ->assertDontSee('About page')
            ->assertDontSee('Payment display')
            ->assertDontSee('Clear Server Cache')
            ->assertDontSee('Hero title');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'payments']))
            ->assertOk()
            ->assertSee('Payment display')
            ->assertDontSee('Primary logo');

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'business']))
            ->assertOk()
            ->assertSee('Café Schedule')
            ->assertDontSee('Opening hours (display text)');
    }

    public function test_branding_save_preserves_other_categories(): void
    {
        $manager = User::factory()->manager()->create();

        WebsiteSetting::query()->where('key', WebsiteSettingKey::HeroTitle->value)->update([
            'value' => 'Keep the hero title',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::PaymentUpiId->value)->update([
            'value' => 'keep@upi',
        ]);
        WebsiteSetting::query()->where('key', WebsiteSettingKey::TaxEnabled->value)->update([
            'value' => '1',
        ]);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'branding',
                WebsiteSettingKey::HeroSubtitle->value => 'CAFFEINE TILL COFFIN.',
                WebsiteSettingKey::BusinessName->value => 'Sip The Soul',
                WebsiteSettingKey::BrandDisplayMode->value => BrandDisplayMode::LogoName->value,
            ])
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']));

        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::HeroTitle->value,
            'value' => 'Keep the hero title',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::BrandDisplayMode->value,
            'value' => BrandDisplayMode::LogoName->value,
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::PaymentUpiId->value,
            'value' => 'keep@upi',
        ]);
        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::TaxEnabled->value,
            'value' => '1',
        ]);
        $this->assertDatabaseMissing('cms_pages', [
            'key' => 'about',
            'body' => 'Welcome to our cafe',
        ]);
    }

    public function test_barista_cannot_manage_website_settings(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.website-settings.edit'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'branding',
                WebsiteSettingKey::HeroTitle->value => 'Nope',
            ])
            ->assertForbidden();
    }

    public function test_manager_can_upload_and_replace_primary_logo(): void
    {
        Storage::fake('public');

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'branding',
                WebsiteSettingKey::BusinessName->value => 'Sip The Soul',
                WebsiteSettingKey::BrandDisplayMode->value => BrandDisplayMode::Logo->value,
                'brand_logo' => UploadedFile::fake()->image('sip-logo.png', 400, 200),
            ])
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']));

        $path = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($path);
        $this->assertTrue(PublicMedia::isManagedRelativePath($path));
        Storage::disk('public')->assertExists($path);

        $content = $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.branding.name', 'Sip The Soul')
            ->assertJsonPath('data.branding.display_mode', BrandDisplayMode::Logo->value)
            ->assertJsonPath('data.branding.favicon_url', null)
            ->json();

        $logoUrl = $content['data']['branding']['logo_url'] ?? null;
        $this->assertIsString($logoUrl);
        $this->assertStringContainsString('/storage/website/', $logoUrl);
        $this->assertSame(PublicMedia::url($path), $logoUrl);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'branding',
                WebsiteSettingKey::BusinessName->value => 'Sip The Soul',
                WebsiteSettingKey::BrandDisplayMode->value => BrandDisplayMode::Logo->value,
                'brand_logo' => UploadedFile::fake()->image('sip-logo-2.webp', 400, 200),
            ])
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']));

        Storage::disk('public')->assertMissing($path);
        $replacement = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($replacement);
        $this->assertNotSame($path, $replacement);
        Storage::disk('public')->assertExists($replacement);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'branding',
                WebsiteSettingKey::BusinessName->value => 'Sip The Soul',
                WebsiteSettingKey::BrandDisplayMode->value => BrandDisplayMode::LogoNameTagline->value,
                'remove_brand_logo' => '1',
            ])
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']));

        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::BrandLogoPath->value,
            'value' => null,
        ]);
        Storage::disk('public')->assertMissing($replacement);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.branding.logo_url', null);
    }

    public function test_primary_logo_accepts_png_jpg_jpeg_and_webp(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        foreach ([
            'mark.png' => UploadedFile::fake()->image('mark.png', 80, 40),
            'mark.jpg' => UploadedFile::fake()->image('mark.jpg', 80, 40),
            'mark.jpeg' => UploadedFile::fake()->image('mark.jpeg', 80, 40),
            'mark.webp' => UploadedFile::fake()->create('mark.webp', 24, 'image/webp'),
        ] as $name => $file) {
            $this->actingAs($manager, 'admin')
                ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                    'brand_logo' => $file,
                ]))
                ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']))
                ->assertSessionDoesntHaveErrors('brand_logo');

            $path = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
            $this->assertIsString($path, $name);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_primary_logo_accepts_svg_and_exposes_storage_url(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => $this->svgUpload($this->safeLogoSvg()),
            ]))
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']));

        $path = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($path);
        $this->assertMatchesRegularExpression('#^website/[0-9a-f-]{36}\.svg$#', $path);
        Storage::disk('public')->assertExists($path);

        $stored = Storage::disk('public')->get($path);
        $this->assertStringContainsString('viewBox="0 0 120 40"', $stored);
        $this->assertStringContainsString('<path', $stored);
        $this->assertStringContainsStringIgnoringCase('linearGradient', $stored);

        $logoUrl = $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->json('data.branding.logo_url');

        $this->assertIsString($logoUrl);
        $this->assertStringEndsWith('.svg', $logoUrl);
        $this->assertSame(PublicMedia::url($path), $logoUrl);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'branding']))
            ->assertOk()
            ->assertSee('Supported: SVG, PNG, JPG, WebP', false)
            ->assertSee('Maximum: 5 MB', false)
            ->assertSee('Transparent PNG, WebP or SVG is recommended', false)
            ->assertSee('<img', false)
            ->assertSee('/storage/website/', false)
            ->assertSee('.svg', false)
            ->assertDontSee('M8 8h104v24H8z', false);
    }

    public function test_primary_logo_accepts_files_under_branding_limit(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('large.png', 80, 40)->size(2200),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => $this->svgUpload($this->safeLogoSvg())->size(2500),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');
    }

    public function test_primary_logo_rejects_files_over_branding_limit(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.website-settings.edit', ['section' => 'branding']))
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('huge.png', 80, 40)->size(PublicMedia::brandLogoMaxKilobytes() + 1),
            ]))
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'branding']))
            ->assertSessionHasErrors([
                'brand_logo' => 'Primary logo must not be larger than 5 MB.',
            ]);
    }

    public function test_hero_image_still_uses_generic_media_limit(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.hero.edit'))
            ->put(route('administrator.hero.update'), [
                WebsiteSettingKey::HeroTitle->value => 'Morning roast',
                'hero_image' => UploadedFile::fake()->image('hero.png', 80, 40)->size(PublicMedia::maxKilobytes() + 1),
            ])
            ->assertSessionHasErrors('hero_image');

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('logo.png', 80, 40)->size(PublicMedia::maxKilobytes() + 1),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');
    }

    public function test_primary_logo_sanitizes_unsafe_svg_and_keeps_artwork(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => $this->svgUpload($this->unsafeLogoSvg()),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');

        $path = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($path);
        $stored = Storage::disk('public')->get($path);
        $this->assertStringContainsString('viewBox="0 0 120 40"', $stored);
        $this->assertStringContainsString('<path', $stored);
        $this->assertStringNotContainsString('<script', strtolower($stored));
        $this->assertStringNotContainsString('onload=', strtolower($stored));
        $this->assertStringNotContainsString('javascript:', strtolower($stored));
        $this->assertStringNotContainsString('foreignobject', strtolower($stored));
    }

    public function test_primary_logo_rejects_invalid_svg(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->from(route('administrator.website-settings.edit', ['section' => 'branding']))
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => $this->svgUpload('<svg><<<<'),
            ]))
            ->assertSessionHasErrors([
                'brand_logo' => 'Primary logo must be SVG, PNG, JPG, JPEG, or WebP.',
            ]);
    }

    public function test_primary_logo_replacement_cleans_up_raster_and_svg(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('first.jpg', 80, 40),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');

        $jpg = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($jpg);
        Storage::disk('public')->assertExists($jpg);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => $this->svgUpload($this->safeLogoSvg()),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');

        Storage::disk('public')->assertMissing($jpg);
        $svg = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($svg);
        $this->assertStringEndsWith('.svg', $svg);
        Storage::disk('public')->assertExists($svg);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), $this->brandingPayload([
                'brand_logo' => UploadedFile::fake()->image('again.png', 80, 40),
            ]))
            ->assertSessionDoesntHaveErrors('brand_logo');

        Storage::disk('public')->assertMissing($svg);
        $png = WebsiteSetting::query()->where('key', WebsiteSettingKey::BrandLogoPath->value)->value('value');
        $this->assertIsString($png);
        $this->assertStringEndsWith('.png', $png);
        Storage::disk('public')->assertExists($png);
    }

    public function test_legacy_website_setting_section_urls_redirect(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'hero']))
            ->assertRedirect(route('administrator.hero.edit'));

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'social']))
            ->assertRedirect(route('administrator.social-links.index'));

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'advanced']))
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'award']));
    }

    public function test_manager_can_manage_hero_without_resetting_existing_values(): void
    {
        Storage::fake('public');
        $manager = User::factory()->manager()->create();

        WebsiteSetting::query()->where('key', WebsiteSettingKey::HeroTitle->value)->update([
            'value' => 'Existing hero title',
        ]);

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.hero.edit'))
            ->assertOk()
            ->assertSee('Existing hero title');

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.hero.update'), [
                WebsiteSettingKey::HeroTitle->value => 'Updated hero title',
            ])
            ->assertRedirect(route('administrator.hero.edit'));

        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::HeroTitle->value,
            'value' => 'Updated hero title',
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.hero.title', 'Updated hero title');
    }

    public function test_award_section_saves_referral_settings_and_advanced_put_maps_to_award(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.website-settings.edit', ['section' => 'award']))
            ->assertOk()
            ->assertSee('Award')
            ->assertSee('Enable customer referrals')
            ->assertDontSee('Clear Server Cache');

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.website-settings.update'), [
                'section' => 'advanced',
                WebsiteSettingKey::ReferralEnabled->value => '0',
            ])
            ->assertRedirect(route('administrator.website-settings.edit', ['section' => 'award']));

        $this->assertDatabaseHas('website_settings', [
            'key' => WebsiteSettingKey::ReferralEnabled->value,
            'value' => '0',
        ]);
    }

    public function test_barista_cannot_open_hero_or_cache_management(): void
    {
        $barista = User::factory()->barista()->create();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.hero.edit'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.cache-management.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function brandingPayload(array $extra = []): array
    {
        return [
            'section' => 'branding',
            WebsiteSettingKey::BusinessName->value => 'Sip The Soul',
            WebsiteSettingKey::BrandDisplayMode->value => BrandDisplayMode::Logo->value,
            ...$extra,
        ];
    }

    private function svgUpload(string $xml): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.svg', $xml);
    }

    private function safeLogoSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#7c5a3b"/>
      <stop offset="1" stop-color="#2c1810"/>
    </linearGradient>
  </defs>
  <g transform="translate(0 0)">
    <path d="M8 8h104v24H8z" fill="url(#g)"/>
  </g>
</svg>
SVG;
    }

    private function unsafeLogoSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 40" onload="alert(1)">
  <script>alert(1)</script>
  <a href="javascript:alert(1)">
    <path d="M8 8h104v24H8z" fill="#7c5a3b"/>
  </a>
  <foreignObject width="40" height="40"><body xmlns="http://www.w3.org/1999/xhtml"><p>x</p></body></foreignObject>
</svg>
SVG;
    }
}
