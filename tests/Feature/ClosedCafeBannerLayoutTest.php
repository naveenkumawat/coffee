<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClosedCafeBannerLayoutTest extends TestCase
{
    public function test_pwa_closed_cafe_banner_uses_shared_status_slot_without_page_offsets(): void
    {
        $layout = File::get(base_path('customer-pwa/src/layouts/AppLayout.tsx'));
        $theme = File::get(base_path('customer-pwa/src/assets/styles/theme.css'));
        $index = File::get(base_path('customer-pwa/index.html'));

        $this->assertStringContainsString('app-status-banners', $layout);
        $this->assertStringContainsString('cafeClosed', $layout);
        $this->assertStringContainsString('availability?.message', $layout);
        $this->assertStringNotContainsString('margin-top: 70px', $layout);
        $this->assertStringNotContainsString('padding-top: 120px', $layout);

        // Closed banner stays in document flow above app-main (not fixed/sticky).
        $this->assertMatchesRegularExpression(
            '/showStatusBanners[\s\S]*app-status-banners[\s\S]*cafeClosed[\s\S]*offline-banner[\s\S]*app-main/',
            $layout,
        );

        $this->assertStringContainsString('--coffee-safe-top', $theme);
        $this->assertStringContainsString('padding-top: var(--coffee-safe-top)', $theme);
        $this->assertStringContainsString('.app-status-banners', $theme);
        $this->assertStringContainsString('viewport-fit=cover', $index);

        // Banner spacing must not collapse through the shell via top margin.
        $this->assertMatchesRegularExpression(
            '/\.offline-banner\s*\{[^}]*margin:\s*0;/',
            $theme,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.offline-banner\s*\{[^}]*margin:\s*0\.75rem/',
            $theme,
        );

        // No page-specific closed-banner offsets on Home/Menu.
        $home = File::get(base_path('customer-pwa/src/pages/HomePage.tsx'));
        $menu = File::get(base_path('customer-pwa/src/pages/MenuPage.tsx'));
        $this->assertStringNotContainsString('offline-banner', $home);
        $this->assertStringNotContainsString('offline-banner', $menu);
        $this->assertStringNotContainsString('paddingTop', $home);
        $this->assertStringNotContainsString('paddingTop', $menu);
    }
}
