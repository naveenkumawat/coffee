<?php

namespace Tests\Unit;

use App\Support\PublicMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
