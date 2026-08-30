<?php

namespace Tests\Feature;

use App\Enums\ProductServingUnit;
use App\Enums\WebsiteSettingKey;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Support\PublicMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_upload_replace_and_remove_product_image(): void
    {
        Storage::fake('public');

        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();

        $create = $this->actingAs($manager, 'admin')->post(route('administrator.products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Media Latte',
            'is_active' => 1,
            'is_available' => 1,
            'variants' => [
                [
                    'name' => 'Regular',
                    'serving_size_value' => '250.000',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '4.50',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
            ],
            'image' => UploadedFile::fake()->image('latte.jpg', 400, 400),
        ]);

        $create->assertRedirect();

        $product = Product::query()->where('slug', 'media-latte')->firstOrFail();
        $this->assertNotNull($product->image_path);
        $this->assertTrue(PublicMedia::isManagedRelativePath($product->image_path));
        Storage::disk('public')->assertExists($product->image_path);

        $firstPath = $product->image_path;

        $this->actingAs($manager, 'admin')->put(route('administrator.products.update', $product), [
            'product_category_id' => $category->id,
            'name' => 'Media Latte',
            'is_active' => 1,
            'is_available' => 1,
            'image_path' => $firstPath,
            'variants' => [
                [
                    'id' => $product->variants()->first()->id,
                    'name' => 'Regular',
                    'serving_size_value' => '250.000',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '4.50',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
            ],
            'image' => UploadedFile::fake()->image('latte-v2.jpg', 400, 400),
        ])->assertRedirect();

        $product->refresh();
        $this->assertNotSame($firstPath, $product->image_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($product->image_path);

        $secondPath = $product->image_path;

        $this->actingAs($manager, 'admin')->put(route('administrator.products.update', $product), [
            'product_category_id' => $category->id,
            'name' => 'Media Latte',
            'is_active' => 1,
            'is_available' => 1,
            'image_path' => $secondPath,
            'remove_image' => 1,
            'variants' => [
                [
                    'id' => $product->variants()->first()->id,
                    'name' => 'Regular',
                    'serving_size_value' => '250.000',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '4.50',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
            ],
        ])->assertRedirect();

        $product->refresh();
        $this->assertNull($product->image_path);
        Storage::disk('public')->assertMissing($secondPath);
    }

    public function test_catalog_api_exposes_absolute_product_image_url(): void
    {
        Storage::fake('public');

        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'image_path' => 'products/demo-cup.webp',
            'is_active' => true,
            'is_available' => true,
        ]);

        Storage::disk('public')->put('products/demo-cup.webp', 'fake-image');

        $response = $this->getJson(route('api.v1.catalog.products.show', $product));

        $response->assertOk();
        $imagePath = $response->json('data.image_path');
        $this->assertIsString($imagePath);
        $this->assertStringContainsString('/storage/products/demo-cup.webp', $imagePath);
        $this->assertMatchesRegularExpression('#^https?://#i', $imagePath);
    }

    public function test_manager_can_upload_payment_qr_image_in_website_settings(): void
    {
        Storage::fake('public');

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')->put(route('administrator.website-settings.update'), [
            WebsiteSettingKey::HeroTitle->value => 'Cafe',
            'payment_qr_image' => UploadedFile::fake()->image('qr.png', 300, 300),
        ])->assertRedirect(route('administrator.website-settings.edit'));

        $stored = WebsiteSetting::query()
            ->where('key', WebsiteSettingKey::PaymentQrImagePath->value)
            ->value('value');

        $this->assertIsString($stored);
        $this->assertTrue(str_starts_with($stored, 'website/'));
        Storage::disk('public')->assertExists($stored);
    }

    public function test_product_image_upload_rejects_invalid_mime(): void
    {
        Storage::fake('public');

        $manager = User::factory()->manager()->create();
        $category = ProductCategory::factory()->create();

        $this->actingAs($manager, 'admin')->post(route('administrator.products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Bad Media',
            'is_active' => 1,
            'is_available' => 1,
            'variants' => [
                [
                    'name' => 'Regular',
                    'serving_size_value' => '250.000',
                    'serving_size_unit' => ProductServingUnit::Milliliter->value,
                    'price' => '4.50',
                    'sort_order' => 1,
                    'is_active' => 1,
                    'is_available' => 1,
                ],
            ],
            'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors(['image']);
    }
}
