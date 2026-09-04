<?php

namespace App\Services\Launch;

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\WebsiteSettingKey;
use App\Models\CafeOperatingHour;
use App\Models\CafeTable;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductFlavour;
use App\Models\SocialLink;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\Payment\PaymentMethodCatalog;
use App\Services\Product\ProductReadinessServiceInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only production launch readiness audit (L2).
 * Does not invent café data or mutate the database.
 */
class LaunchReadinessService implements LaunchReadinessServiceInterface
{
    public function __construct(
        protected ProductReadinessServiceInterface $productReadiness,
    ) {}

    public function evaluate(): LaunchReadinessReport
    {
        $findings = [];
        $areas = [];
        $settings = $this->settingMap();

        $this->auditBusiness($settings, $findings, $areas);
        $this->auditPayment($settings, $findings, $areas);
        $this->auditHours($settings, $findings, $areas);
        $this->auditFulfilment($settings, $findings, $areas);
        $this->auditCms($settings, $findings, $areas);
        $this->auditSocial($findings, $areas);
        $this->auditCatalog($findings, $areas);
        $this->auditLaunchMenuDoc($findings, $areas);
        $this->auditDining($settings, $findings, $areas);
        $this->auditStaff($findings, $areas);
        $this->auditDemoContamination($findings, $areas);
        $this->auditTax($settings, $findings, $areas);
        $this->auditPromotionsHome($findings, $areas);
        $this->auditDeliveryFee($findings, $areas);

        $catalog = $this->productReadiness->catalogSummary();

        $summary = [
            'environment' => app()->environment(),
            'business_name' => $this->filled($settings[WebsiteSettingKey::BusinessName->value] ?? null),
            'active_products' => Product::query()->where('is_active', true)->count(),
            'products_total' => $catalog['total'],
            'products_ready' => $catalog['ready'],
            'products_incomplete' => $catalog['incomplete'],
            'active_categories' => ProductCategory::query()->where('is_active', true)->count(),
            'active_tables' => CafeTable::query()->where('is_active', true)->count(),
            'dining_enabled' => $this->truthy($settings[WebsiteSettingKey::FulfilmentDineInEnabled->value] ?? null),
            'demo_coffee_local_users' => User::query()->where('email', 'like', '%@coffee.local')->count(),
            'blocker_count' => collect($findings)->where('severity', 'blocker')->count(),
            'required_count' => collect($findings)->where('severity', 'required')->count(),
        ];

        return new LaunchReadinessReport($findings, $areas, $summary);
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditBusiness(array $settings, array &$findings, array &$areas): void
    {
        $name = $this->filled($settings[WebsiteSettingKey::BusinessName->value] ?? null);
        $slogan = $this->filled($settings[WebsiteSettingKey::HeroSubtitle->value] ?? null);

        if ($name === null) {
            $this->add($findings, 'business.name', 'blocker', 'business', 'Website Settings business_name is empty.');
            $areas[] = $this->area('business_identity', 'missing_real_data', 'Brand name not set in Website Settings.');
        } else {
            $areas[] = $this->area('business_identity', 'ready', "business_name={$name}".($slogan ? '; slogan set' : ''));
        }

        foreach ([
            [WebsiteSettingKey::BusinessPhone, 'business.phone', 'Customer phone'],
            [WebsiteSettingKey::BusinessWhatsappNumber, 'business.whatsapp', 'WhatsApp number'],
            [WebsiteSettingKey::BusinessAddress, 'business.address', 'Pickup / visit address'],
        ] as [$key, $code, $label]) {
            if ($this->filled($settings[$key->value] ?? null) === null) {
                $this->add($findings, $code, 'required', 'business', "{$label} missing in Website Settings.");
            }
        }

        if ($this->filled($settings[WebsiteSettingKey::BusinessEmail->value] ?? null) === null) {
            $this->add($findings, 'business.email', 'optional', 'business', 'Public email empty (optional if not shown).');
        }

        if ($this->filled($settings[WebsiteSettingKey::BusinessAboutShort->value] ?? null) === null) {
            $this->add($findings, 'business.about', 'required', 'business', 'Short about text missing.');
        }

        $areas[] = $this->area(
            'contact_details',
            $this->filled($settings[WebsiteSettingKey::BusinessPhone->value] ?? null)
                && $this->filled($settings[WebsiteSettingKey::BusinessWhatsappNumber->value] ?? null)
                && $this->filled($settings[WebsiteSettingKey::BusinessAddress->value] ?? null)
                ? 'ready'
                : 'missing_real_data',
            'Phone / WhatsApp / address required before public launch.',
        );
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditPayment(array $settings, array &$findings, array &$areas): void
    {
        $catalog = app(PaymentMethodCatalog::class);
        $manualEnabled = $catalog->isEnabled(PaymentMethod::Manual);
        $anyOnlineEnabled = collect(PaymentMethod::onlineCases())
            ->contains(fn (PaymentMethod $method): bool => $catalog->isEnabled($method));

        $upi = $this->filled($settings[WebsiteSettingKey::PaymentUpiId->value] ?? null)
            ?? $this->filled(is_string(config('coffee.payments.upi_id')) ? config('coffee.payments.upi_id') : null);
        $qr = $this->filled($settings[WebsiteSettingKey::PaymentQrImagePath->value] ?? null)
            ?? $this->filled(is_string(config('coffee.payments.qr_image_path')) ? config('coffee.payments.qr_image_path') : null);
        $instructions = $this->filled($settings[WebsiteSettingKey::PaymentInstructions->value] ?? null)
            ?? $this->filled(is_string(config('coffee.payments.instructions')) ? config('coffee.payments.instructions') : null);

        if ($manualEnabled) {
            if ($upi === null) {
                $this->add($findings, 'payment.upi', 'blocker', 'payment', 'Manual UPI is enabled but Payment UPI ID is missing.');
            }

            if ($qr === null) {
                $this->add($findings, 'payment.qr', 'blocker', 'payment', 'Manual UPI is enabled but Payment QR image path is missing.');
            } elseif (! $this->mediaExists($qr)) {
                $this->add($findings, 'payment.qr_file', 'blocker', 'payment', 'Payment QR path is set but file is missing on the public media disk.');
            }

            if ($instructions === null) {
                $this->add($findings, 'payment.instructions', 'required', 'payment', 'Payment instructions missing.');
            }

            if ($this->filled($settings[WebsiteSettingKey::PaymentWhatsappNumber->value] ?? null) === null
                && $this->filled(is_string(config('coffee.payments.whatsapp_number')) ? config('coffee.payments.whatsapp_number') : null) === null) {
                $this->add($findings, 'payment.whatsapp', 'required', 'payment', 'Payment WhatsApp number missing.');
            }
        }

        foreach ($catalog->adminDiagnostics() as $row) {
            if (! ($row['enabled'] ?? false)) {
                continue;
            }

            if (! ($row['configured'] ?? false)) {
                $this->add(
                    $findings,
                    'payment.'.$row['code'].'.incomplete',
                    'blocker',
                    'payment',
                    $row['name'].' is enabled but configuration is incomplete.',
                );
            }

            if (($row['type'] ?? null) === 'online' && ($row['mode'] ?? null) === 'test' && app()->environment('production')) {
                $this->add(
                    $findings,
                    'payment.'.$row['code'].'.test_mode',
                    'required',
                    'payment',
                    $row['name'].' is enabled in test/sandbox mode on a production environment.',
                );
            }
        }

        $readyManual = $manualEnabled && $upi && $qr && $this->mediaExists((string) $qr);
        $readyOnline = collect($catalog->adminDiagnostics())
            ->contains(fn (array $row): bool => ($row['type'] ?? null) === 'online'
                && ($row['enabled'] ?? false)
                && ($row['configured'] ?? false));

        if (! $manualEnabled && ! $anyOnlineEnabled && ! $catalog->isEnabled(PaymentMethod::Cash)) {
            $this->add($findings, 'payment.none', 'blocker', 'payment', 'No payment methods are enabled.');
        }

        $areas[] = $this->area(
            'payment_methods',
            ($readyManual || $readyOnline || ($catalog->isEnabled(PaymentMethod::Cash) && $catalog->isConfigured(PaymentMethod::Cash)))
                ? 'ready'
                : 'missing_real_data',
            'ENABLED ≠ AVAILABLE. Gateways use env credentials; Manual UPI uses website settings/config.',
        );
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditHours(array $settings, array &$findings, array &$areas): void
    {
        $textHours = $this->filled($settings[WebsiteSettingKey::BusinessOpeningHours->value] ?? null);
        $structuredDays = CafeOperatingHour::query()->count();

        if ($textHours === null && $structuredDays === 0) {
            $this->add(
                $findings,
                'hours.missing',
                'blocker',
                'hours',
                'No opening hours: set Website Settings business_opening_hours and/or Cafe Operating Hours rows.',
            );
            $areas[] = $this->area('opening_hours', 'missing_real_data', 'Neither CMS hours nor structured schedule configured.');
        } else {
            $areas[] = $this->area(
                'opening_hours',
                'ready',
                ($textHours ? 'CMS hours set; ' : '')."structured_days={$structuredDays}",
            );
        }

        if ($structuredDays > 0 && $structuredDays < 7) {
            $this->add(
                $findings,
                'hours.partial_week',
                'required',
                'hours',
                "Cafe operating hours cover {$structuredDays}/7 days — confirm intentional closures.",
            );
        }
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditFulfilment(array $settings, array &$findings, array &$areas): void
    {
        $areas[] = $this->area(
            'fulfilment_takeaway_delivery',
            'ready',
            'Takeaway + Delivery capabilities are implemented; confirm café will offer both.',
        );

        $disclaimer = $this->filled($settings[WebsiteSettingKey::FulfilmentDeliveryDisclaimer->value] ?? null);
        if ($disclaimer === null) {
            $this->add(
                $findings,
                'fulfilment.delivery_disclaimer',
                'required',
                'fulfilment',
                'Delivery disclaimer empty in Website Settings (config fallback may show infrastructure wording).',
            );
        }

        $areas[] = $this->area(
            'fulfilment_disclaimer',
            $disclaimer ? 'ready' : 'missing_real_data',
            'Approve café-facing third-party delivery copy.',
        );
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditCms(array $settings, array &$findings, array &$areas): void
    {
        foreach ([
            [WebsiteSettingKey::PagesTerms, 'cms.terms', 'blocker', 'Terms page (approved legal copy)'],
            [WebsiteSettingKey::PagesPrivacy, 'cms.privacy', 'blocker', 'Privacy page (approved legal copy)'],
            [WebsiteSettingKey::PagesAbout, 'cms.about', 'required', 'About page'],
            [WebsiteSettingKey::PagesContact, 'cms.contact', 'required', 'Visit / Contact page'],
            [WebsiteSettingKey::PagesFaq, 'cms.faq', 'required', 'FAQ page'],
        ] as [$key, $code, $severity, $label]) {
            if ($this->filled($settings[$key->value] ?? null) === null) {
                $this->add($findings, $code, $severity, 'cms', "{$label} content missing in Website Settings.");
            }
        }

        $areas[] = $this->area(
            'cms_pages',
            $this->filled($settings[WebsiteSettingKey::PagesTerms->value] ?? null)
                && $this->filled($settings[WebsiteSettingKey::PagesPrivacy->value] ?? null)
                ? 'ready'
                : 'missing_real_data',
            'Do not invent legal Terms/Privacy.',
        );
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditSocial(array &$findings, array &$areas): void
    {
        $activeWithUrl = SocialLink::query()
            ->where('is_active', true)
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->count();

        if ($activeWithUrl === 0) {
            $this->add(
                $findings,
                'social.urls',
                'required',
                'social',
                'No active social links with URLs (Facebook / Instagram / WhatsApp shells may exist inactive).',
            );
            $areas[] = $this->area('social_links', 'missing_real_data', 'Shells OK; real URLs still required.');
        } else {
            $areas[] = $this->area('social_links', 'ready', "active_with_url={$activeWithUrl}");
        }
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditCatalog(array &$findings, array &$areas): void
    {
        $summary = $this->productReadiness->catalogSummary();
        $activeCount = Product::query()->where('is_active', true)->count();
        $categoryCount = ProductCategory::query()->count();
        $flavourCount = ProductFlavour::query()->count();

        if ($summary['total'] === 0) {
            $this->add(
                $findings,
                'catalog.empty',
                'blocker',
                'catalog',
                'No products in database. Fill docs/launch-menu.md then enter catalog via Administrator (do not invent).',
            );
            $areas[] = $this->area('catalog_products', 'missing_real_data', '0 products — launch menu source incomplete.');
        } else {
            $areas[] = $this->area(
                'catalog_products',
                $summary['incomplete'] === 0 && $activeCount > 0 ? 'ready' : 'missing_real_data',
                "total={$summary['total']} ready={$summary['ready']} incomplete={$summary['incomplete']} active={$activeCount}",
            );
        }

        if ($activeCount === 0 && $summary['total'] > 0) {
            $this->add(
                $findings,
                'catalog.no_active',
                'blocker',
                'catalog',
                'Products exist but none are active for sale.',
            );
        }

        foreach ($summary['items'] as $item) {
            $product = Product::query()->where('name', $item['name'])->first();
            $severity = ($product?->is_active ?? false) ? 'blocker' : 'required';
            $this->add(
                $findings,
                'catalog.incomplete.'.str($item['name'])->slug()->toString(),
                $severity,
                'catalog',
                'Product "'.$item['name'].'" incomplete: '.implode('; ', $item['missing']),
            );
        }

        $activeWithoutStation = Product::query()
            ->where('is_active', true)
            ->whereNull('preparation_station')
            ->count();

        if ($activeWithoutStation > 0) {
            $this->add(
                $findings,
                'catalog.station',
                'blocker',
                'catalog',
                "{$activeWithoutStation} active product(s) missing preparation_station.",
            );
        }

        $areas[] = $this->area(
            'catalog_categories',
            $categoryCount > 0 ? 'ready' : 'missing_real_data',
            "categories={$categoryCount}",
        );
        $areas[] = $this->area(
            'catalog_flavours',
            $flavourCount > 0 ? 'ready' : 'optional_deferred',
            "flavours={$flavourCount} (optional if café does not use flavours)",
        );
        $areas[] = $this->area(
            'recipes_inventory',
            $summary['ready'] > 0 ? 'ready' : 'missing_real_data',
            'Recipes required before activating sellable products; do not invent opening stock.',
        );
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditLaunchMenuDoc(array &$findings, array &$areas): void
    {
        $path = base_path('docs/launch-menu.md');
        if (! File::exists($path)) {
            $this->add($findings, 'launch_menu.missing_file', 'blocker', 'launch_menu', 'docs/launch-menu.md is missing.');
            $areas[] = $this->area('launch_menu_source', 'missing_real_data', 'launch-menu.md missing.');

            return;
        }

        $contents = File::get($path);
        $confirmed = ! str_contains($contents, 'Status: STOPPED')
            && ! str_contains($contents, 'No confirmed real menu list');

        // Heuristic: empty decision tables still have blank pipes after headers.
        $hasNamedProductRow = (bool) preg_match('/^\| [^|]+ \| [^|]+ \|/m', $contents)
            && ! str_contains($contents, '| Product name | Category |');

        if (! $confirmed || str_contains($contents, 'awaiting café decisions')) {
            $this->add(
                $findings,
                'launch_menu.unconfirmed',
                'blocker',
                'launch_menu',
                'docs/launch-menu.md is still awaiting café decisions (no confirmed categories/products/prices).',
            );
            $areas[] = $this->area('launch_menu_source', 'missing_real_data', 'Treat launch-menu.md as incomplete — do not invent catalog.');
        } else {
            $areas[] = $this->area(
                'launch_menu_source',
                $hasNamedProductRow ? 'ready' : 'missing_real_data',
                'launch-menu.md present; verify decision tables are filled.',
            );
        }
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditDining(array $settings, array &$findings, array &$areas): void
    {
        $diningEnabled = $this->truthy($settings[WebsiteSettingKey::FulfilmentDineInEnabled->value] ?? null);
        $activeTables = CafeTable::query()->where('is_active', true)->count();

        if ($diningEnabled && $activeTables === 0) {
            $this->add(
                $findings,
                'dining.no_tables',
                'blocker',
                'dining',
                'Dining is enabled but there are no active café tables.',
            );
        }

        if (! $diningEnabled) {
            $areas[] = $this->area('dining_tables', 'optional_deferred', 'Dine-in disabled; table count not required until enabled.');
            $this->add(
                $findings,
                'dining.table_count_unknown',
                'optional',
                'dining',
                'Real café table count/labels not supplied — do not invent before enabling dine-in.',
            );
        } else {
            $areas[] = $this->area(
                'dining_tables',
                $activeTables > 0 ? 'ready' : 'missing_real_data',
                "active_tables={$activeTables}",
            );
        }
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditStaff(array &$findings, array &$areas): void
    {
        $roles = [
            UserRole::Owner->value => 'Administrator/Owner',
            UserRole::Operator->value => 'Operator',
            UserRole::Barista->value => 'Barista',
            UserRole::Chef->value => 'Chef',
            UserRole::Waiter->value => 'Waiter',
        ];

        $missingRoles = [];
        foreach ($roles as $role => $label) {
            $exists = User::query()->where('role', $role)->where('is_active', true)->exists();
            if (! $exists) {
                $missingRoles[] = $label;
            }
        }

        if ($missingRoles !== []) {
            $severity = app()->environment('production') ? 'blocker' : 'required';
            $this->add(
                $findings,
                'staff.roles',
                $severity,
                'staff',
                'Missing active staff roles: '.implode(', ', $missingRoles).'. Create real accounts with strong passwords (never demo *@coffee.local / password).',
            );
        }

        $areas[] = $this->area(
            'staff_accounts',
            $missingRoles === [] ? 'ready' : 'missing_real_data',
            'Production must not use demo *@coffee.local accounts.',
        );
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditDemoContamination(array &$findings, array &$areas): void
    {
        $demoUsers = User::query()->where('email', 'like', '%@coffee.local')->count();

        if ($demoUsers > 0 && app()->environment('production')) {
            $this->add(
                $findings,
                'demo.users',
                'blocker',
                'demo',
                "{$demoUsers} *@coffee.local user(s) present in production — remove before launch.",
            );
        } elseif ($demoUsers > 0) {
            $this->add(
                $findings,
                'demo.users_local',
                'optional',
                'demo',
                "{$demoUsers} *@coffee.local demo user(s) present (expected only in local/testing).",
            );
        }

        $areas[] = $this->area(
            'demo_data_isolation',
            app()->environment(['local', 'testing']) || $demoUsers === 0 ? 'ready' : 'demo_only',
            'DemoSeeder blocked outside local/testing; production seed is structural only.',
        );
    }

    /**
     * @param  array<string, ?string>  $settings
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditTax(array $settings, array &$findings, array &$areas): void
    {
        $enabled = $this->truthy($settings[WebsiteSettingKey::TaxEnabled->value] ?? null);
        if ($enabled) {
            if ($this->filled($settings[WebsiteSettingKey::TaxPercent->value] ?? null) === null) {
                $this->add($findings, 'tax.percent', 'blocker', 'tax', 'Tax enabled but tax_percent missing.');
            }
            if ($this->filled($settings[WebsiteSettingKey::TaxGstin->value] ?? null) === null) {
                $this->add($findings, 'tax.gstin', 'required', 'tax', 'Tax enabled but GSTIN empty — confirm with café.');
            }
        }

        $areas[] = $this->area(
            'tax_gst',
            'optional_deferred',
            $enabled ? 'Tax enabled — verify percent/GSTIN with café.' : 'Tax currently off or unset — confirm café GST policy (do not invent).',
        );
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditPromotionsHome(array &$findings, array &$areas): void
    {
        $sections = HomeSection::query()->where('is_active', true)->count();
        if ($sections === 0) {
            $this->add(
                $findings,
                'home.sections',
                'optional',
                'homepage',
                'No active homepage sections (can complete after catalog exists).',
            );
        }

        $areas[] = $this->area(
            'homepage_promotions',
            $sections > 0 ? 'ready' : 'optional_deferred',
            "active_home_sections={$sections}; promotions optional for day-one.",
        );
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     * @param  list<array{area: string, status: string, notes: string}>  $areas
     */
    protected function auditDeliveryFee(array &$findings, array &$areas): void
    {
        // Checkout hard-codes delivery_fee_amount = null; business rule is third-party customer-paid.
        $this->add(
            $findings,
            'delivery.fee_not_collected',
            'optional',
            'delivery',
            'App does not collect café delivery fee (orders.delivery_fee_amount remains null). Confirm third-party customer-paid model is still the launch rule.',
        );

        $areas[] = $this->area(
            'delivery_fee',
            'optional_deferred',
            'No café delivery-fee setting; field reserved. Not a blocker if third-party pay-separately remains true.',
        );
    }

    /**
     * @return array<string, ?string>
     */
    protected function settingMap(): array
    {
        return WebsiteSetting::query()
            ->pluck('value', 'key')
            ->map(static fn ($value): ?string => is_string($value) ? $value : (filled($value) ? (string) $value : null))
            ->all();
    }

    protected function filled(?string $value): ?string
    {
        $trimmed = is_string($value) ? trim($value) : '';

        return $trimmed === '' ? null : $trimmed;
    }

    protected function truthy(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    protected function mediaExists(string $path): bool
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return true;
        }

        $disk = (string) config('coffee.media.disk', 'public');

        return Storage::disk($disk)->exists($path);
    }

    /**
     * @return array{area: string, status: string, notes: string}
     */
    protected function area(string $area, string $status, string $notes): array
    {
        return [
            'area' => $area,
            'status' => $status,
            'notes' => $notes,
        ];
    }

    /**
     * @param  list<array{code: string, severity: string, message: string, area: string}>  $findings
     */
    protected function add(array &$findings, string $code, string $severity, string $area, string $message): void
    {
        $findings[] = [
            'code' => $code,
            'severity' => $severity,
            'area' => $area,
            'message' => $message,
        ];
    }
}
