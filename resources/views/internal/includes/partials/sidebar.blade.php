@php
    $user = auth('admin')->user();
    $dashboardRoute = match ($panel) {
        'administrator' => route('administrator.dashboard'),
        'operator' => route('operator.dashboard'),
        'chef' => route('chef.dashboard'),
        'waiter' => route('waiter.dashboard'),
        default => route('barista.dashboard'),
    };

    $navigation = match ($panel) {
        'administrator' => [
            [
                'heading' => 'Dashboard',
                'items' => [
                    ['label' => 'Analytics', 'route' => 'administrator.dashboard', 'pattern' => 'administrator.dashboard*', 'icon' => 'ki-chart-line'],
                ],
            ],
            [
                'heading' => 'Storefront',
                'items' => $user?->canManageProducts() ? [
                    ['label' => 'Homepage Sections', 'route' => 'administrator.home-sections.index', 'pattern' => 'administrator.home-sections.*', 'icon' => 'ki-abstract-26'],
                ] : [],
            ],
            [
                'heading' => 'Products',
                'items' => $user?->canManageProducts() ? [
                    ['label' => 'Categories', 'route' => 'administrator.products.categories.index', 'pattern' => 'administrator.products.categories.*', 'icon' => 'ki-category'],
                    ['label' => 'Flavours', 'route' => 'administrator.products.flavours.index', 'pattern' => 'administrator.products.flavours.*', 'icon' => 'ki-cup'],
                    ['label' => 'Tags', 'route' => 'administrator.products.tags.index', 'pattern' => 'administrator.products.tags.*', 'icon' => 'ki-price-tag'],
                    ['label' => 'Add-ons', 'route' => 'administrator.add-ons.index', 'pattern' => 'administrator.add-ons.*', 'icon' => 'ki-plus-square'],
                    ['label' => 'Ratings', 'route' => 'administrator.products.ratings.index', 'pattern' => 'administrator.products.ratings.*', 'icon' => 'ki-star'],
                    ['label' => 'Products', 'route' => 'administrator.products.index', 'pattern' => ['administrator.products.index', 'administrator.products.create', 'administrator.products.store', 'administrator.products.show', 'administrator.products.edit', 'administrator.products.update'], 'icon' => 'ki-basket'],
                    ['label' => 'Recipes', 'route' => 'administrator.recipes.index', 'pattern' => 'administrator.recipes.*', 'icon' => 'ki-book'],
                ] : [],
            ],
            [
                'heading' => 'Ingredients',
                'items' => $user?->canManageIngredients() ? [
                    ['label' => 'Categories', 'route' => 'administrator.ingredients.categories.index', 'pattern' => 'administrator.ingredients.categories.*', 'icon' => 'ki-book-open'],
                    ['label' => 'Brands', 'route' => 'administrator.ingredients.brands.index', 'pattern' => 'administrator.ingredients.brands.*', 'icon' => 'ki-tag'],
                    ['label' => 'Ingredients', 'route' => 'administrator.ingredients.index', 'pattern' => 'administrator.ingredients.*', 'icon' => 'ki-flask'],
                ] : [],
            ],
            [
                'heading' => 'Orders',
                'items' => $user?->canViewOrders() ? [
                    ['label' => 'Orders', 'route' => 'administrator.orders.index', 'pattern' => 'administrator.orders.*', 'icon' => 'ki-delivery-2'],
                    ['label' => 'Dining Sessions', 'route' => 'administrator.dining-sessions.index', 'pattern' => 'administrator.dining-sessions.*', 'icon' => 'ki-coffee'],
                    ...(
                        $user?->canViewFinancialReports()
                            ? [
                                ['label' => 'Financial Report', 'route' => 'administrator.reports.financial.index', 'pattern' => 'administrator.reports.financial.*', 'icon' => 'ki-chart-simple'],
                                ['label' => 'Recommendation Performance', 'route' => 'administrator.reports.recommendations.index', 'pattern' => 'administrator.reports.recommendations.*', 'icon' => 'ki-chart-line-up'],
                                ['label' => 'Campaign Performance', 'route' => 'administrator.reports.campaigns.index', 'pattern' => 'administrator.reports.campaigns.*', 'icon' => 'ki-notification-status'],
                                ['label' => 'Inventory & Product Analytics', 'route' => 'administrator.reports.inventory-products.index', 'pattern' => 'administrator.reports.inventory-products.*', 'icon' => 'ki-chart-pie-simple'],
                                ['label' => 'Operational Performance', 'route' => 'administrator.reports.operational-performance.index', 'pattern' => 'administrator.reports.operational-performance.*', 'icon' => 'ki-timer'],
                            ]
                            : []
                    ),
                ] : [],
            ],
            [
                'heading' => 'Inventory',
                'items' => $user?->canViewInventory() ? [
                    ['label' => 'Overview', 'route' => 'administrator.inventory.index', 'pattern' => 'administrator.inventory.index', 'icon' => 'ki-abstract-41'],
                    ['label' => 'History', 'route' => 'administrator.inventory.history', 'pattern' => 'administrator.inventory.history', 'icon' => 'ki-time'],
                    ['label' => 'Refill Requests', 'route' => 'administrator.inventory.refill-requests.index', 'pattern' => 'administrator.inventory.refill-requests.*', 'icon' => 'ki-delivery-3'],
                ] : [],
            ],
            [
                'heading' => 'Administration',
                'items' => array_values(array_filter([
                    $user?->canManageUsers()
                        ? ['label' => 'Users', 'route' => 'administrator.users.index', 'pattern' => 'administrator.users.*', 'icon' => 'ki-profile-circle']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Website Settings', 'route' => 'administrator.website-settings.edit', 'pattern' => 'administrator.website-settings.*', 'icon' => 'ki-setting-2']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Social Links', 'route' => 'administrator.social-links.index', 'pattern' => 'administrator.social-links.*', 'icon' => 'ki-share']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Café Tables', 'route' => 'administrator.cafe-tables.index', 'pattern' => 'administrator.cafe-tables.*', 'icon' => 'ki-tablet']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Offers & Promotions', 'route' => 'administrator.promotions.index', 'pattern' => 'administrator.promotions.*', 'icon' => 'ki-discount']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Loyalty Operations', 'route' => 'administrator.loyalty-operations.index', 'pattern' => 'administrator.loyalty-operations.*', 'icon' => 'ki-chart-pie-4']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Loyalty Rewards', 'route' => 'administrator.loyalty-rewards.index', 'pattern' => 'administrator.loyalty-rewards.*', 'icon' => 'ki-gift']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Campaigns', 'route' => 'administrator.campaigns.index', 'pattern' => 'administrator.campaigns.*', 'icon' => 'ki-notification-on']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Audience Segments', 'route' => 'administrator.segments.index', 'pattern' => 'administrator.segments.*', 'icon' => 'ki-people']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Referrals', 'route' => 'administrator.referrals.index', 'pattern' => 'administrator.referrals.*', 'icon' => 'ki-people']
                        : null,
                    $user?->canManageWebsiteSettings()
                        ? ['label' => 'Café Schedule', 'route' => 'administrator.cafe-schedule.index', 'pattern' => 'administrator.cafe-schedule.*', 'icon' => 'ki-calendar']
                        : null,
                ])),
            ],
        ],
        'operator' => [
            [
                'heading' => 'Dashboard',
                'items' => [
                    ['label' => 'Overview', 'route' => 'operator.dashboard', 'pattern' => 'operator.dashboard*', 'icon' => 'ki-chart-line'],
                ],
            ],
            [
                'heading' => 'Operations',
                'items' => [
                    ['label' => 'Orders', 'route' => 'operator.orders.index', 'pattern' => 'operator.orders.*', 'icon' => 'ki-delivery-2'],
                    ['label' => 'Dining Sessions', 'route' => 'operator.dining-sessions.index', 'pattern' => 'operator.dining-sessions.*', 'icon' => 'ki-coffee'],
                    ['label' => 'Preparation', 'route' => 'operator.preparations.index', 'pattern' => 'operator.preparations.*', 'icon' => 'ki-chef'],
                    ['label' => 'Today Reconciliation', 'route' => 'operator.reconciliation.index', 'pattern' => 'operator.reconciliation.*', 'icon' => 'ki-chart-simple'],
                    ['label' => 'Inventory & Product Ops', 'route' => 'operator.reports.inventory-products.index', 'pattern' => 'operator.reports.inventory-products.*', 'icon' => 'ki-chart-pie-simple'],
                    ['label' => 'Operational Performance', 'route' => 'operator.reports.operational-performance.index', 'pattern' => 'operator.reports.operational-performance.*', 'icon' => 'ki-timer'],
                ],
            ],
            [
                'heading' => 'Inventory',
                'items' => [
                    ['label' => 'Overview', 'route' => 'operator.inventory.index', 'pattern' => 'operator.inventory.*', 'icon' => 'ki-abstract-41'],
                    ['label' => 'Refill Requests', 'route' => 'operator.refill-requests.index', 'pattern' => 'operator.refill-requests.*', 'icon' => 'ki-delivery-3'],
                ],
            ],
        ],
        'waiter' => [
            [
                'heading' => 'Dashboard',
                'items' => [
                    ['label' => 'Overview', 'route' => 'waiter.dashboard', 'pattern' => 'waiter.dashboard*', 'icon' => 'ki-chart-line'],
                ],
            ],
            [
                'heading' => 'Dining',
                'items' => [
                    ['label' => 'Tables', 'route' => 'waiter.tables.index', 'pattern' => 'waiter.tables.*', 'icon' => 'ki-tablet'],
                    ['label' => 'Sessions', 'route' => 'waiter.sessions.index', 'pattern' => 'waiter.sessions.*', 'icon' => 'ki-coffee'],
                ],
            ],
        ],
        'chef' => [
            [
                'heading' => 'Dashboard',
                'items' => [
                    ['label' => 'Overview', 'route' => 'chef.dashboard', 'pattern' => 'chef.dashboard*', 'icon' => 'ki-chart-line'],
                ],
            ],
            [
                'heading' => 'Kitchen',
                'items' => [
                    ['label' => 'Preparation Queue', 'route' => 'chef.preparations.index', 'pattern' => 'chef.preparations.*', 'icon' => 'ki-chef'],
                ],
            ],
        ],
        'barista' => [
            [
                'heading' => 'Dashboard',
                'items' => [
                    ['label' => 'Overview', 'route' => 'barista.dashboard', 'pattern' => 'barista.dashboard*', 'icon' => 'ki-chart-line'],
                ],
            ],
            [
                'heading' => 'Bar',
                'items' => [
                    ['label' => 'Preparation Queue', 'route' => 'barista.preparations.index', 'pattern' => 'barista.preparations.*', 'icon' => 'ki-coffee'],
                ],
            ],
            [
                'heading' => 'Catalog',
                'items' => [
                    ['label' => 'Products', 'route' => 'barista.products.index', 'pattern' => 'barista.products.*', 'icon' => 'ki-basket'],
                    ['label' => 'Recipes', 'route' => 'barista.recipes.index', 'pattern' => 'barista.recipes.*', 'icon' => 'ki-book'],
                ],
            ],
            [
                'heading' => 'Inventory',
                'items' => [
                    ['label' => 'Overview', 'route' => 'barista.inventory.index', 'pattern' => 'barista.inventory.*', 'icon' => 'ki-abstract-41'],
                    ['label' => 'Refill Requests', 'route' => 'barista.refill-requests.index', 'pattern' => 'barista.refill-requests.*', 'icon' => 'ki-delivery-3'],
                ],
            ],
        ],
        default => [],
    };

    $logoutRoute = match ($panel) {
        'administrator' => route('administrator.logout'),
        'operator' => route('operator.logout'),
        'chef' => route('chef.logout'),
        'waiter' => route('waiter.logout'),
        default => route('barista.logout'),
    };

    $panelLabel = match ($panel) {
        'administrator' => 'Administrator',
        'operator' => 'Operator',
        'chef' => 'Chef',
        'waiter' => 'Waiter',
        default => 'Barista',
    };
@endphp

<div id="kt_app_sidebar" class="app-sidebar flex-column" data-kt-drawer="true" data-kt-drawer-name="app-sidebar" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="225px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_app_sidebar_mobile_toggle">
    <div class="app-sidebar-logo px-6" id="kt_app_sidebar_logo">
        <a href="{{ $dashboardRoute }}" class="text-decoration-none d-flex flex-column">
            <span class="fs-3 fw-bold text-white">{{ config('app.name') }}</span>
            <span class="text-gray-400 fs-8 text-uppercase">{{ $panelLabel }}</span>
        </a>

        <div id="kt_app_sidebar_toggle" class="app-sidebar-toggle btn btn-icon btn-shadow btn-sm btn-color-muted btn-active-color-primary body-bg h-30px w-30px position-absolute top-50 start-100 translate-middle rotate" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="app-sidebar-minimize">
            <i class="ki-duotone ki-double-left fs-2 rotate-180">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
        </div>
    </div>

    <div class="app-sidebar-menu overflow-hidden flex-column-fluid">
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper hover-scroll-overlay-y my-5" data-kt-scroll="true" data-kt-scroll-activate="true" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer" data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
            <div class="menu menu-column menu-rounded menu-sub-indention px-3" id="kt_app_sidebar_menu" data-kt-menu="true" data-kt-menu-expand="false">
                @foreach ($navigation as $section)
                    @if (filled($section['items']))
                        <div class="menu-item pt-5">
                            <div class="menu-content">
                                <span class="menu-heading fw-bold text-uppercase fs-7">{{ $section['heading'] }}</span>
                            </div>
                        </div>

                        @foreach ($section['items'] as $item)
                            @php
                                $isActive = collect((array) $item['pattern'])->contains(
                                    fn (string $pattern): bool => request()->routeIs($pattern),
                                );
                            @endphp
                            <div class="menu-item">
                                <a class="menu-link {{ $isActive ? 'active here show' : '' }}" href="{{ route($item['route']) }}">
                                    <span class="menu-icon">
                                        <i class="ki-duotone {{ $item['icon'] }} fs-2">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                            <span class="path6"></span>
                                        </i>
                                    </span>
                                    <span class="menu-title">{{ $item['label'] }}</span>
                                </a>
                            </div>
                        @endforeach
                    @endif
                @endforeach

                <div class="menu-item pt-5">
                    <div class="menu-content">
                        <span class="menu-heading fw-bold text-uppercase fs-7">Session</span>
                    </div>
                </div>

                <div class="menu-item">
                    <a class="menu-link" href="{{ route('home') }}">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-shop fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                                <span class="path4"></span>
                            </i>
                        </span>
                        <span class="menu-title">Storefront</span>
                    </a>
                </div>

                <div class="menu-item">
                    <button class="menu-link border-0 bg-transparent w-100 text-start" data-bs-toggle="modal" data-bs-target="#internalFoundationModal" type="button">
                        <span class="menu-icon">
                            <i class="ki-duotone ki-information-5 fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                        </span>
                        <span class="menu-title">UI Foundation</span>
                    </button>
                </div>

                <div class="menu-item">
                    <form method="POST" action="{{ $logoutRoute }}">
                        @csrf
                        <button class="menu-link border-0 bg-transparent w-100 text-start" type="submit">
                            <span class="menu-icon">
                                <i class="ki-duotone ki-exit-right fs-2">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                            </span>
                            <span class="menu-title">Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
