<?php

namespace Tests\Feature;

use App\Enums\CmsPageKey;
use App\Enums\WebsiteSettingKey;
use App\Models\CmsPage;
use App\Models\User;
use App\Models\WebsiteSetting;
use Database\Seeders\CmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdministratorCmsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_pages_exist_and_cannot_be_deleted_by_missing_destroy_route(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager, 'admin')
            ->get(route('administrator.cms-pages.index'))
            ->assertOk()
            ->assertSee('About')
            ->assertSee('Contact / Visit')
            ->assertSee('FAQ')
            ->assertSee('Terms')
            ->assertSee('Privacy');

        foreach (CmsPageKey::ordered() as $key) {
            $this->assertDatabaseHas('cms_pages', [
                'key' => $key->value,
                'is_system' => 1,
            ]);
        }

        $this->assertFalse(
            collect(Route::getRoutes())->contains(
                fn ($route): bool => $route->getName() === 'administrator.cms-pages.destroy',
            ),
        );
    }

    public function test_manager_can_edit_pages_and_faq_repeater(): void
    {
        $manager = User::factory()->manager()->create();
        $about = CmsPage::query()->where('key', CmsPageKey::About->value)->firstOrFail();
        $faq = CmsPage::query()->where('key', CmsPageKey::Faq->value)->firstOrFail();

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.cms-pages.update', $about), [
                'title' => 'Our story',
                'seo_title' => 'About Sip The Soul',
                'meta_description' => 'Neighborhood espresso.',
                'body' => '<p>Welcome to <strong>Sip The Soul</strong>.</p><script>alert(1)</script>',
                'is_published' => '1',
            ])
            ->assertRedirect(route('administrator.cms-pages.edit', $about));

        $about->refresh();
        $this->assertSame('Our story', $about->title);
        $this->assertTrue($about->is_published);
        $this->assertStringContainsString('<strong>Sip The Soul</strong>', (string) $about->body);
        $this->assertStringNotContainsString('<script>', (string) $about->body);

        $this->actingAs($manager, 'admin')
            ->put(route('administrator.cms-pages.update', $faq), [
                'title' => 'FAQ',
                'is_published' => '1',
                'faq_items' => [
                    [
                        'question' => 'How do I order?',
                        'answer' => 'Use the app.',
                        'sort_order' => 10,
                        'is_active' => '1',
                    ],
                    [
                        'question' => 'Hidden?',
                        'answer' => 'Nope.',
                        'sort_order' => 20,
                        'is_active' => '0',
                    ],
                ],
            ])
            ->assertRedirect(route('administrator.cms-pages.edit', $faq));

        $this->assertDatabaseHas('cms_faq_items', [
            'cms_page_id' => $faq->getKey(),
            'question' => 'How do I order?',
            'is_active' => 1,
        ]);

        $payload = $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->json('data');

        $this->assertIsString($payload['pages']['about'] ?? null);
        $this->assertStringContainsString('Sip The Soul', strip_tags($payload['pages']['about']));
        $this->assertSame('How do I order?', $payload['faq_items'][0]['question'] ?? null);
        $this->assertCount(1, $payload['faq_items']);
    }

    public function test_unpublished_pages_are_hidden_from_customers(): void
    {
        $page = CmsPage::query()->where('key', CmsPageKey::Terms->value)->firstOrFail();
        $page->update([
            'body' => '<p>Secret terms</p>',
            'is_published' => false,
        ]);

        $this->getJson(route('api.v1.content.show'))
            ->assertOk()
            ->assertJsonPath('data.pages.terms', null)
            ->assertJsonPath('data.page_meta.terms.is_published', false);
    }

    public function test_legacy_website_setting_page_copy_is_preserved_into_cms(): void
    {
        WebsiteSetting::query()->where('key', WebsiteSettingKey::PagesAbout->value)->update([
            'value' => 'Legacy about copy from settings.',
        ]);
        CmsPage::query()->where('key', CmsPageKey::About->value)->update([
            'body' => null,
            'is_published' => false,
        ]);

        $this->seed(CmsPageSeeder::class);

        $body = CmsPage::query()->where('key', CmsPageKey::About->value)->value('body');
        $this->assertIsString($body);
        $this->assertStringContainsString('Legacy about copy from settings.', strip_tags($body));
        $this->assertDatabaseHas('cms_pages', [
            'key' => 'about',
            'is_published' => 1,
        ]);
    }

    public function test_barista_cannot_manage_cms_pages(): void
    {
        $barista = User::factory()->barista()->create();
        $about = CmsPage::query()->where('key', CmsPageKey::About->value)->firstOrFail();

        $this->actingAs($barista, 'admin')
            ->get(route('administrator.cms-pages.index'))
            ->assertForbidden();

        $this->actingAs($barista, 'admin')
            ->put(route('administrator.cms-pages.update', $about), [
                'title' => 'Nope',
            ])
            ->assertForbidden();
    }
}
