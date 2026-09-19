<?php

namespace Tests\Unit;

use App\Support\PublicMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicMediaTest extends TestCase
{
    public function test_url_preserves_absolute_urls_and_builds_storage_urls(): void
    {
        $this->assertNull(PublicMedia::url(null));
        $this->assertNull(PublicMedia::url('  '));
        $this->assertSame(
            'https://cdn.example.com/cup.webp',
            PublicMedia::url('https://cdn.example.com/cup.webp'),
        );

        $url = PublicMedia::url('products/cup.webp');
        $this->assertIsString($url);
        $this->assertStringContainsString('/storage/products/cup.webp', $url);

        $this->app['url']->forceRootUrl('http://localhost/coffee');
        $subdirectory = PublicMedia::url('website/logo.png');
        $this->assertSame('http://localhost/coffee/storage/website/logo.png', $subdirectory);
        $this->app['url']->forceRootUrl('http://localhost');
    }

    public function test_store_writes_uuid_filename_on_public_disk(): void
    {
        Storage::fake('public');

        $path = PublicMedia::store(
            UploadedFile::fake()->image('Cup Photo.JPEG', 200, 200),
            PublicMedia::DIRECTORY_PRODUCTS,
        );

        $this->assertMatchesRegularExpression('#^products/[0-9a-f-]{36}\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_store_keeps_png_and_webp_extensions(): void
    {
        Storage::fake('public');

        $png = PublicMedia::store(
            UploadedFile::fake()->image('logo.png', 120, 80),
            PublicMedia::DIRECTORY_WEBSITE,
        );
        $webp = PublicMedia::store(
            UploadedFile::fake()->create('mark.webp', 20, 'image/webp'),
            PublicMedia::DIRECTORY_WEBSITE,
        );

        $this->assertMatchesRegularExpression('#^website/[0-9a-f-]{36}\.png$#', $png);
        $this->assertMatchesRegularExpression('#^website/[0-9a-f-]{36}\.webp$#', $webp);
        Storage::disk('public')->assertExists($png);
        Storage::disk('public')->assertExists($webp);
    }

    public function test_generic_upload_rules_stay_at_catalog_limit_without_svg(): void
    {
        $this->assertSame(512, PublicMedia::maxKilobytes());
        $this->assertSame(['jpg', 'jpeg', 'png', 'webp'], PublicMedia::allowedExtensions());
        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], PublicMedia::allowedMimes());
        $this->assertSame(5120, PublicMedia::brandLogoMaxKilobytes());
        $this->assertContains('svg', PublicMedia::brandLogoAllowedExtensions());
        $this->assertStringContainsString('max:512', implode('|', PublicMedia::uploadRules()));
        $this->assertStringNotContainsString('svg', implode(',', PublicMedia::allowedExtensions()));
    }

    public function test_store_brand_logo_writes_sanitized_svg_uuid_path(): void
    {
        Storage::fake('public');

        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">
  <script>alert(1)</script>
  <path d="M0 0h10v10H0z" fill="#7c5a3b"/>
</svg>
SVG;

        $path = PublicMedia::storeBrandLogo(
            UploadedFile::fake()->createWithContent('Sip The Soul.svg', $svg),
        );

        $this->assertMatchesRegularExpression('#^website/[0-9a-f-]{36}\.svg$#', $path);
        $this->assertStringNotContainsString('Sip The Soul', $path);
        $stored = Storage::disk('public')->get($path);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $stored);
        $this->assertStringContainsString('<path', $stored);
        $this->assertStringNotContainsString('<script', strtolower($stored));
        $this->assertStringNotContainsString('onload=', strtolower($stored));
        $this->assertSame(PublicMedia::url($path), url('storage/'.$path));
    }

    public function test_generic_store_rejects_svg(): void
    {
        Storage::fake('public');

        $this->expectException(ValidationException::class);

        PublicMedia::store(
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            PublicMedia::DIRECTORY_PRODUCTS,
        );
    }

    public function test_delete_managed_only_removes_catalog_paths(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/keep-or-not.webp', 'a');
        Storage::disk('public')->put('other/external.webp', 'b');

        PublicMedia::deleteManaged('https://cdn.example.com/x.webp');
        PublicMedia::deleteManaged('other/external.webp');
        Storage::disk('public')->assertExists('other/external.webp');

        PublicMedia::deleteManaged('products/keep-or-not.webp');
        Storage::disk('public')->assertMissing('products/keep-or-not.webp');
    }
}
